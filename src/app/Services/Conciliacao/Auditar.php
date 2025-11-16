<?php

namespace App\Services\Conciliacao;

use DateTime;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class Auditar
{
    private const TOLERANCE = 0.01;
    private const DEFAULT_DISCOUNT_THRESHOLD = 0.1;
    private const DEFAULT_MONTHS_TO_CONSIDER_CLOSED = 1;
    private const SIMILARITY_THRESHOLD = 80.0;

    public static function execute(
        string $diskPath,
        float $discountThreshold = self::DEFAULT_DISCOUNT_THRESHOLD,
        int $monthsToConsiderClosed = self::DEFAULT_MONTHS_TO_CONSIDER_CLOSED,
        array $ignoreAccounts = []
    ): array {
        $accounts = self::parseAccounts($diskPath, $ignoreAccounts);
        $results = [];

        foreach ($accounts as $account) {
            $results[] = self::processAccount($account, $discountThreshold, $monthsToConsiderClosed);
            // Liberar memória após cada conta processada
            gc_collect_cycles();
        }

        return $results;
    }

    public static function relatorio(
        string $diskPath,
        float $discountThreshold = self::DEFAULT_DISCOUNT_THRESHOLD,
        int $monthsToConsiderClosed = self::DEFAULT_MONTHS_TO_CONSIDER_CLOSED,
        array $ignoreAccounts = []
    ): View {
        return view('relatorios.balanco', [
            'contas' => self::execute($diskPath, $discountThreshold, $monthsToConsiderClosed, $ignoreAccounts)
        ]);
    }

    private static function parseAccounts(string $path, array $ignoreAccounts): array
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (in_array($extension, ['csv', 'xlsx'])) {
            return self::parseAccountsExcelOptimized($path, $ignoreAccounts);
        }

        $content = Storage::disk('uploads')->get($path);
        return self::parseAccountsText($content, $ignoreAccounts);
    }

    private static function parseAccountsExcelOptimized(string $diskPath, array $ignoreAccounts): array
    {
        $filePath = Storage::disk('uploads')->path($diskPath);

        // Aumentar memória temporariamente apenas para leitura do Excel
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $rows = Excel::toArray([], $filePath)[0];
            $accounts = [];
            $current = null;
            $rowCount = 0;

            foreach ($rows as $fields) {
                $rowCount++;
                $line = implode("\t", $fields);

                if (str_starts_with($line, 'Conta:')) {
                    if ($current) {
                        $accountNum = self::extractAccountNumber($current['header']);
                        if (!in_array($accountNum, $ignoreAccounts, true)) {
                            $accounts[] = $current;
                        }
                        // Liberar memória da conta anterior
                        $current = null;
                    }
                    $current = ['header' => $line, 'transactions' => []];
                } elseif ($current) {
                    $current['transactions'][] = $line;
                }

                // Liberar memória a cada 500 linhas processadas
                if ($rowCount % 500 === 0) {
                    gc_collect_cycles();
                }
            }

            if ($current) {
                $accountNum = self::extractAccountNumber($current['header']);
                if (!in_array($accountNum, $ignoreAccounts, true)) {
                    $accounts[] = $current;
                }
            }

            return $accounts;
        } finally {
            // Restaurar limite de memória original
            ini_set('memory_limit', $originalMemoryLimit);
            gc_collect_cycles();
        }
    }

    private static function parseAccountsText(string $content, array $ignoreAccounts): array
    {
        $lines = preg_split('/\R/', trim($content));
        $accounts = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'Conta:')) {
                if ($current) {
                    $accountNum = self::extractAccountNumber($current['header']);
                    if (!in_array($accountNum, $ignoreAccounts, true)) {
                        $accounts[] = $current;
                    }
                }
                $current = ['header' => $line, 'transactions' => []];
            } elseif ($current) {
                $current['transactions'][] = $line;
            }
        }

        if ($current) {
            $accountNum = self::extractAccountNumber($current['header']);
            if (!in_array($accountNum, $ignoreAccounts, true)) {
                $accounts[] = $current;
            }
        }

        return $accounts;
    }

    private static function extractAccountNumber(string $headerLine): string
    {
        $headerWithoutPrefix = trim(str_replace('Conta:', '', $headerLine));
        $dashPos = strpos($headerWithoutPrefix, '-');
        if ($dashPos === false) {
            return $headerWithoutPrefix;
        }
        return trim(substr($headerWithoutPrefix, 0, $dashPos));
    }

    private static function processAccount(
        array $account,
        float $discountThreshold,
        int $monthsToConsiderClosed
    ): array {
        $header = str_replace('Conta:', '', $account['header']);
        $transactions = $account['transactions'];

        $saldoAnterior = self::extractPreviousBalance($transactions);
        $totals = self::initializeTotals($saldoAnterior);
        $notas = [];
        $problemas = self::initializeProblems();

        foreach ($transactions as $transaction) {
            if (!self::isValidTransactionLine($transaction)) {
                continue;
            }

            $transactionData = self::parseTransactionLine($transaction);
            self::updateTotals($totals, $transactionData);
            self::processNotes($transactionData, $notas);
            self::processInvoice($transactionData, $notas);
            self::processPayment($transactionData, $notas, $problemas);
        }

        $notasResumo = self::summarizeInvoices($notas, $discountThreshold, $monthsToConsiderClosed);
        $processedPayments = self::processPaymentsWithoutNotes($problemas['sem_nota'], $saldoAnterior);

        return self::buildAccountResult(
            accountName: trim($header),
            totals: $totals,
            problemas: $problemas,
            notasResumo: $notasResumo,
            processedPayments: $processedPayments,
            notas: $notas
        );
    }

    private static function initializeTotals(float $saldoAnterior): array
    {
        return [
            'saldo_anterior' => $saldoAnterior,
            'saldo_corrente' => $saldoAnterior,
            'total_debitos' => 0.0,
            'total_creditos' => 0.0,
            'saldo_banco' => null
        ];
    }

    private static function initializeProblems(): array
    {
        return [
            'sem_nota' => [],
            'pagamento_numeracao_errada' => [],
            'ajustados' => [],
            'com_juros' => []
        ];
    }

    private static function extractPreviousBalance(array $transactions): float
    {
        foreach ($transactions as $transaction) {
            if (str_contains($transaction, 'Saldo Anterior')) {
                $parts = preg_split('/\s+/', $transaction);
                return self::parseValue(end($parts));
            }
        }
        return 0.0;
    }

    private static function isValidTransactionLine(string $line): bool
    {
        return preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}/', $line) === 1
            && !str_contains($line, 'Saldo Anterior');
    }

    private static function parseTransactionLine(string $transaction): array
    {
        $fields = array_map('trim', explode("\t", $transaction));

        return [
            'date' => self::formatDate($fields[0] ?? ''),
            'type' => $fields[1] ?? '',
            'code' => $fields[2] ?? null,
            'document' => $fields[3] ?? '',
            'debit' => self::parseValue($fields[4] ?? '0'),
            'credit' => self::parseValue($fields[5] ?? '0'),
            'bank_balance' => isset($fields[6]) && $fields[6] !== '' ? self::parseValue($fields[6]) : null
        ];
    }

    private static function updateTotals(array &$totals, array $transactionData): void
    {
        $totals['total_creditos'] += $transactionData['credit'];
        $totals['total_debitos'] += $transactionData['debit'];
        $totals['saldo_corrente'] += $transactionData['debit'] - $transactionData['credit'];

        if ($transactionData['bank_balance'] !== null) {
            $totals['saldo_banco'] = $transactionData['bank_balance'];
        }
    }

    private static function processInvoice(array $transactionData, array &$notas): void
    {
        if (
            str_contains($transactionData['type'], 'PAGAMENTO') ||
            str_contains($transactionData['type'], 'DUPLICATA')
        ) {
            return;
        }

        $invoiceNumber = self::extractInvoiceNumber($transactionData['type']);
        if (!$invoiceNumber) {
            return;
        }

        if (isset($notas[$invoiceNumber])) {
            $notas[$invoiceNumber]['valor_total'] = round(
                $notas[$invoiceNumber]['valor_total'] + $transactionData['credit'],
                2
            );
            $notas[$invoiceNumber]['data'] = $transactionData['date'];
        }
    }

    private static function processNotes(array $transactionData, array &$notas): void
    {
        if (
            !str_contains($transactionData['type'], 'PAGAMENTO') &&
            !str_contains($transactionData['type'], 'DUPLICATA') &&
            str_contains($transactionData['type'], 'NOTA FISCAL')
        ) {
            $noteNumber = self::extractInvoiceNumber($transactionData['type']);
            if (!$noteNumber) {
                return;
            }

            if (!isset($notas[$noteNumber])) {
                $notas[$noteNumber] = [
                    'valor_total' => 0.0,
                    'data' => $transactionData['date'],
                    'qtd_parcela' => 0,
                    'pagamentos' => [],
                    'com_juros' => false
                ];
            }
        }
    }

    private static function processPayment(array $transactionData, array &$notas, array &$problemas): void
    {
        if (!self::isPaymentTransaction($transactionData['type'])) {
            return;
        }

        $invoiceNumber = self::extractInvoiceNumber($transactionData['type']);
        $amountPaid = round($transactionData['debit'], 2);

        if ($invoiceNumber && !isset($notas[$invoiceNumber])) {
            $candidateNote = self::findCandidateNote($invoiceNumber, $amountPaid, $notas);

            if (!empty($candidateNote)) {
                $problemas['pagamento_numeracao_errada'][] = [
                    'data' => $transactionData['date'],
                    'doc' => $transactionData['document'],
                    'num' => $invoiceNumber,
                    'valor_pago' => $amountPaid,
                    'possivel_correta' => [
                        'numero' => $candidateNote['numero'],
                        'valor_total' => $candidateNote['valor_total']
                    ]
                ];
            }
        }

        if (!$invoiceNumber || !isset($notas[$invoiceNumber])) {
            self::handlePaymentWithoutInvoice($transactionData, $problemas, $invoiceNumber, $amountPaid);
            return;
        }

        self::processValidPayment($transactionData, $notas, $problemas, $invoiceNumber, $amountPaid);
    }

    private static function isPaymentTransaction(string $type): bool
    {
        return str_contains($type, 'PAGAMENTO DUPLICATA') ||
            str_contains($type, 'PAGAMENTO NOTA FISCAL') ||
            str_contains($type, 'LANCTO REF. DESCONTO OBTIDO REF. NFE') ||
            str_contains($type, 'DEVOLUÇÃO DE COMPRAS CONF. NF');
    }

    private static function findCandidateNote(string $invoiceNumber, float $amountPaid, array $notas): ?array
    {
        foreach ($notas as $candidateNumber => $invoiceData) {
            similar_text($invoiceNumber, $candidateNumber, $percent);
            $isCandidateLonger = strlen($candidateNumber) > strlen($invoiceNumber);

            $valueMatch = abs($invoiceData['valor_total'] - $amountPaid) < self::TOLERANCE;

            if ($percent > self::SIMILARITY_THRESHOLD || ($valueMatch && $isCandidateLonger)) {
                return [
                    'numero' => $candidateNumber,
                    'valor_total' => $invoiceData['valor_total']
                ];
            }
        }

        return null;
    }

    private static function handlePaymentWithoutInvoice(
        array $transactionData,
        array &$problemas,
        ?string $invoiceNumber,
        float $amountPaid
    ): void {
        $problemas['sem_nota'][] = [
            'data' => $transactionData['date'],
            'doc' => $transactionData['document'],
            'num' => $invoiceNumber,
            'valor_pago' => $amountPaid
        ];
    }

    private static function processValidPayment(
        array $transactionData,
        array &$notas,
        array &$problemas,
        string $invoiceNumber,
        float $amountPaid
    ): void {
        $invoice = &$notas[$invoiceNumber];
        $previouslyPaid = self::calculatePreviouslyPaid($invoice['pagamentos']);
        $pending = round($invoice['valor_total'] - $previouslyPaid, 2);

        $totalAfterPayment = round($previouslyPaid + $amountPaid, 2);

        if ($totalAfterPayment > $invoice['valor_total'] && $transactionData['credit'] == 0.0) {
            $interest = round($totalAfterPayment - $invoice['valor_total'], 2);

            if (abs($interest) >= self::TOLERANCE) {
                $problemas['com_juros'][] = [
                    'data' => $transactionData['date'],
                    'doc' => $transactionData['document'],
                    'num' => $invoiceNumber,
                    'code' => $transactionData['code'],
                    'valor_total_nota' => $invoice['valor_total'],
                    'valor_pendente' => $pending,
                    'valor_pago' => $totalAfterPayment,
                    'juros' => $interest,
                    'parcela' => $invoice['qtd_parcela'] + 1,
                ];
                $invoice['com_juros'] = true;
            }
        }

        if ($transactionData['credit'] > 0.0) {
            $problemas['ajustados'][] = [
                'data' => $transactionData['date'],
                'doc' => $transactionData['document'],
                'num' => $invoiceNumber,
                'valor_total_nota' => $invoice['valor_total'],
                'deb' => $transactionData['debit'],
                'cred' => $transactionData['credit'],
                'parcela' => $invoice['qtd_parcela'] + 1,
            ];
        }

        $invoice['pagamentos'][] = [
            'data' => $transactionData['date'],
            'valor' => $amountPaid,
            'doc' => $transactionData['document'],
        ];
        $invoice['qtd_parcela']++;
    }

    private static function calculatePreviouslyPaid(array $payments): float
    {
        return round(array_sum(array_column($payments, 'valor')), 2);
    }

    private static function extractInvoiceNumber(string $text): ?string
    {
        preg_match('/\s*(\d+)/', $text, $matches);
        return isset($matches[1]) ? (string)$matches[1] : null;
    }

    private static function summarizeInvoices(
        array $notas,
        float $discountThreshold,
        int $monthsToConsiderClosed
    ): array {
        $summary = ['nao' => [], 'parc' => [], 'pag' => [], 'desc' => [], 'juros' => []];

        foreach ($notas as $number => $invoice) {
            $total = round($invoice['valor_total'], 2);
            $paid = self::calculatePreviouslyPaid($invoice['pagamentos']);

            if ($total <= 0) {
                continue;
            }

            $item = [
                'numero' => $number,
                'data' => $invoice['data'],
                'valor_total' => $total,
                'valor_pago' => $paid,
                'pagamentos' => $invoice['pagamentos']
            ];

            $pending = round($total - $paid, 2);

            if (!empty($invoice['com_juros'])) {
                $summary['juros'][] = $item;
                continue;
            }

            if ($paid == 0) {
                $summary['nao'][] = $item;
            } elseif ($pending > 0) {
                if (
                    self::isInvoiceClosed($item, $monthsToConsiderClosed) &&
                    self::isDiscountAcceptable($total, $pending, $discountThreshold)
                ) {
                    $summary['desc'][] = $item;
                } else {
                    $summary['parc'][] = $item;
                }
            } else {
                $summary['pag'][] = $item;
            }
        }

        return $summary;
    }

    private static function processPaymentsWithoutNotes(array $paymentsWithoutNotes, float $previousBalance): array
    {
        usort($paymentsWithoutNotes, function ($a, $b) {
            $dateA = DateTime::createFromFormat('d/m/Y', $a['data']);
            $dateB = DateTime::createFromFormat('d/m/Y', $b['data']);
            return $dateA <=> $dateB;
        });

        $previousPayments = [];
        $remainingPayments = [];
        $totalPaid = 0.0;

        foreach ($paymentsWithoutNotes as $payment) {
            $amount = $payment['valor_pago'] ?? 0.0;

            if ($totalPaid + $amount <= $previousBalance + self::TOLERANCE) {
                $previousPayments[] = $payment;
                $totalPaid += $amount;
            } else {
                $remainingPayments[] = $payment;
            }
        }

        return [
            'anteriores' => $previousPayments,
            'restantes' => $remainingPayments
        ];
    }

    private static function buildAccountResult(
        string $accountName,
        array $totals,
        array $problemas,
        array $notasResumo,
        array $processedPayments,
        array $notas
    ): array {
        return [
            'conta' => $accountName,
            'saldo_anterior' => $totals['saldo_anterior'],
            'saldo_corrente' => round($totals['saldo_corrente'], 2),
            'saldo_banco' => $totals['saldo_banco'],
            'saldo_balanceado' => abs(($totals['total_debitos'] + $totals['saldo_anterior']) - $totals['total_creditos']) < self::TOLERANCE,
            'total_debitos' => round($totals['total_debitos'], 2),
            'total_creditos' => round($totals['total_creditos'], 2),
            'ajustados' => $problemas['ajustados'],
            'juros' => $problemas['com_juros'],
            'pagamentos_sem_notas' => $processedPayments['restantes'],
            'pagamentos_anteriores' => $processedPayments['anteriores'],
            'pagamento_numeracao_errada' => $problemas['pagamento_numeracao_errada'],
            'notas_nao_pagas' => $notasResumo['nao'],
            'notas_parcialmente_pagas' => $notasResumo['parc'],
            'notas_pagas_com_desconto' => $notasResumo['desc'],
            'notas_pagas_com_juros' => $notasResumo['juros'],
            'notas_pagas' => $notasResumo['pag'],
            'valor_total_notas' => round(array_sum(array_column($notas, 'valor_total')), 2),
            'valor_pago_notas' => round(array_sum(array_map(function ($n) {
                return self::calculatePreviouslyPaid($n['pagamentos']);
            }, $notas)), 2),
        ];
    }

    private static function isInvoiceClosed(array $invoice, int $monthsToConsiderClosed): bool
    {
        if (empty($invoice['pagamentos'])) {
            return false;
        }

        $today = new DateTime();
        $lastPaymentDate = max(array_map(function ($payment) {
            return DateTime::createFromFormat('d/m/Y', $payment['data']);
        }, $invoice['pagamentos']));

        $difference = $today->diff($lastPaymentDate);
        $monthsDifference = $difference->y * 12 + $difference->m;

        return $monthsDifference >= $monthsToConsiderClosed;
    }

    private static function isDiscountAcceptable(float $total, float $pending, float $discountThreshold): bool
    {
        if ($total <= 0) {
            return false;
        }

        $discountPercentage = $pending / $total;
        return $discountPercentage <= $discountThreshold;
    }

    private static function parseValue(string $value): float
    {
        $clean = str_replace(['.', ','], ['', '.'], $value);
        return round((float) $clean, 2);
    }

    private static function formatDate(string $date): string
    {
        $dateTime = DateTime::createFromFormat('d/m/Y', $date) ?: DateTime::createFromFormat('Y-m-d', $date);
        return $dateTime ? $dateTime->format('d/m/Y') : $date;
    }
}
