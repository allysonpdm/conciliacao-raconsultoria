<?php

namespace App\Services\Conciliacao;

use App\Models\Conciliacao;
use App\Models\ContaConciliada;
use App\Models\Empresa;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Store
{
    private const DB_BATCH_SIZE = 1000;
    public static function execute(
        Empresa $empresa,
        string $pathFile,
    ): Conciliacao {


        $conciliacao = self::saveConciliacao(
            empresa: $empresa,
            pathFile: $pathFile,
        );

        self::processAuditData(
            conciliacao: $conciliacao
        );

        return $conciliacao; #self::loadConciliacaoRelations($conciliacao);
    }

    private static function saveConciliacao(
        Empresa $empresa,
        string $pathFile
    ): Conciliacao {

        $conciliacao = Conciliacao::updateOrCreate(attributes: [
            'empresa_id' => $empresa->id,
        ], values: [
            'file' => $pathFile,
        ]);

        $conciliacao->arquivoExportacao()
            ->updateOrCreate([
                'conciliacao_id' => $conciliacao->id,
            ]);

        return $conciliacao;
    }

    private static function processAuditData(
        Conciliacao $conciliacao
    ): void {
        $auditoria = Auditar::execute(
            diskPath: $conciliacao->file,
            discountThreshold: $conciliacao->empresa->config->percentual_min_pago,
            monthsToConsiderClosed: $conciliacao->empresa->config->meses_tolerancia_desconto,
        );

        $conciliacao->contas()->delete();

        foreach ($auditoria as $conta) {
            $contaModel = self::firstOrCreateConta($conciliacao, $conta);
            self::processContaData($contaModel, $conta);
        }
    }

    private static function firstOrCreateConta(Conciliacao $conciliacao, array $conta)
    {
        $parts = explode(' - ', $conta['conta']);

        $contaModel = $conciliacao->contas()
            ->firstOrCreate(
                attributes: [
                    'numero' => (int) $parts[0],
                ],
                values: [
                    'mascara_contabil' => $parts[1] ?? null,
                    'nome' => $parts[2],
                    'saldo_anterior' => $conta['saldo_anterior'] ?? 0.00,
                    'saldo_banco' => $conta['saldo_banco'] ?? 0.00,
                    'total_debitos' => $conta['total_debitos'] ?? 0.00,
                    'total_creditos' => $conta['total_creditos'] ?? 0.00,
                    'valor_total_notas' => $conta['valor_total_notas'] ?? 0.00,
                    'valor_pago_notas' => $conta['valor_pago_notas'] ?? 0.00,
                    'balanceado' => $conta['saldo_balanceado'],
                ]
            );

        return $contaModel;
    }

    private static function processContaData($contaModel, array $conta): void
    {
        self::processNotas($contaModel, $conta);
        self::processPagamentos($contaModel, $conta);
        self::processAjustes($contaModel, $conta);
        self::processErrosPagamentos($contaModel, $conta);
    }

    private static function processNotas($contaModel, array $conta): void
    {
        $notasToInsert = self::prepareNotasData($contaModel->id, $conta);

        if (!empty($notasToInsert)) {
            foreach (array_chunk($notasToInsert, self::DB_BATCH_SIZE) as $chunk) {
                $contaModel->notas()->insert($chunk);
            }
        }
    }

    private static function prepareNotasData(int $contaId, array $conta): array
    {
        $typeMap = [
            'notas_pagas' => 'paga',
            'notas_nao_pagas' => 'nao_paga',
            'notas_pagas_com_desconto' => 'desconto_paga',
            'notas_parcialmente_pagas' => 'parcialmente_paga',
            'notas_pagas_com_juros' => 'com_juros_paga',
        ];

        $notasToInsert = [];

        foreach ($typeMap as $key => $tipo) {
            foreach ($conta[$key] ?? [] as $nota) {
                $notasToInsert[] = [
                    'conta_conciliada_id' => $contaId,
                    'numero' => $nota['numero'],
                    'data' => Carbon::createFromFormat('d/m/Y', $nota['data']),
                    'valor' => $nota['valor_total'],
                    'valor_pago' => $nota['valor_pago'] ?? null,
                    'tipo' => $tipo
                ];
            }
        }

        return $notasToInsert;
    }

    private static function processPagamentos($contaModel, array $conta): void
    {
        $pagamentosToInsert = self::preparePagamentosData($contaModel->id, $conta);

        if (!empty($pagamentosToInsert)) {
            // chunk the upsert to avoid exceeding the DB placeholders limit (MySQL ~65k)
            foreach (array_chunk($pagamentosToInsert, self::DB_BATCH_SIZE) as $chunk) {
                $contaModel->pagamentos()->upsert($chunk, ['conta_conciliada_id', 'doc'], ['tipo']);
            }
        }
    }

    private static function preparePagamentosData(int $contaId, array $conta): array
    {
        $typeMap = [
            'pagamentos_sem_notas' => 'nota não encontrada',
            'pagamentos_anteriores' => 'anterior',
            'juros' => 'com juros',
        ];

        $pagamentosToInsert = [];

        foreach ($typeMap as $key => $tipo) {
            foreach ($conta[$key] ?? [] as $pagamento) {
                $pagamentosToInsert[] = [
                    'conta_conciliada_id' => $contaId,
                    'data' => Carbon::createFromFormat('d/m/Y', $pagamento['data']),
                    'doc' => $pagamento['doc'],
                    'parcela' => $pagamento['parcela'] ?? 1,
                    'numero_nota' => $pagamento['num'] ?? null,
                    'valor_nota' => $pagamento['valor_total_nota'] ?? null,
                    'valor_pago' => $pagamento['valor_total'] ?? $pagamento['valor_pago'],
                    'valor_juros' => $pagamento['juros'] ?? null,
                    'code' => $pagamento['code'] ?? null,
                    'tipo' => $tipo
                ];
            }
        }

        $notaKeys = [
            'notas_pagas' => 'pago com nota',
            //'notas_nao_pagas' => ,
            'notas_pagas_com_desconto' => 'com descontos',
            'notas_parcialmente_pagas' => 'parcialmente pago',
            'notas_pagas_com_juros' => 'com juros'
        ];

        foreach ($notaKeys as $notaKey => $tipo) {
            foreach ($conta[$notaKey] ?? [] as $nota) {
                $numeroNota = $nota['numero'] ?? null;
                foreach ($nota['pagamentos'] ?? [] as $idx => $parcela) {
                    $pagamentosToInsert[] = [
                        'conta_conciliada_id' => $contaId,
                        'data' => Carbon::createFromFormat('d/m/Y', $parcela['data']),
                        'doc' => $parcela['doc'],
                        'parcela' => $idx + 1,
                        'numero_nota' => $numeroNota,
                        'valor_nota' => $nota['valor_total'] ?? null,
                        'valor_pago' => $parcela['valor'],
                        'valor_juros' => null,
                        'code' => null,
                        'tipo' => $tipo
                    ];
                }
            }
        }

        return $pagamentosToInsert;
    }

    private static function processAjustes(ContaConciliada $contaModel, array $conta): void
    {
        $ajustesToInsert = self::prepareAjustesData($contaModel->id, $conta);

        if (!empty($ajustesToInsert)) {
            foreach (array_chunk($ajustesToInsert, self::DB_BATCH_SIZE) as $chunk) {
                $contaModel->ajustes()->insert($chunk);
            }
        }
    }

    private static function prepareAjustesData(int $contaId, array $conta): array
    {
        $ajustesToInsert = [];

        foreach ($conta['ajustados'] ?? [] as $ajuste) {
            $ajustesToInsert[] = [
                'conta_conciliada_id' => $contaId,
                'data' => Carbon::createFromFormat('d/m/Y', $ajuste['data']),
                'doc' => $ajuste['doc'],
                'numero_nota' => $ajuste['num'] ?? null,
                'valor' => $ajuste['cred'] > $ajuste['deb'] ? $ajuste['cred'] : $ajuste['deb'],
                'tipo' => $ajuste['cred'] > $ajuste['deb'] ? 'C' : 'D'
            ];
        }

        return $ajustesToInsert;
    }

    private static function processErrosPagamentos(ContaConciliada $contaModel, array $conta): void
    {
        if (empty($conta['pagamento_numeracao_errada'])) {
            return;
        }

        $errosPagamentosToInsert = self::prepareErrosPagamentosData($contaModel->id, $conta);

        if (!empty($errosPagamentosToInsert)) {
            foreach (array_chunk($errosPagamentosToInsert, self::DB_BATCH_SIZE) as $chunk) {
                $contaModel->errosPagamentos()->insert($chunk);
            }
        }
    }

    private static function prepareErrosPagamentosData(int $contaId, array $conta): array
    {
        $errosPagamentosToInsert = [];

        foreach ($conta['pagamento_numeracao_errada'] as $erro) {
            $errosPagamentosToInsert[] = [
                'conta_conciliada_id' => $contaId,
                'data' => Carbon::createFromFormat('d/m/Y', $erro['data']),
                'doc' => $erro['doc'],
                'numero_nota' => $erro['num'] ?? null,
                'valor_pago' => $erro['valor_pago'],
                'sugestao_numero_nota' => $erro['possivel_correta']['numero']
            ];
        }

        return $errosPagamentosToInsert;
    }

    private static function loadConciliacaoRelations(Conciliacao $conciliacao): Conciliacao
    {
        return $conciliacao->load([
            'contas.notas',
            'contas.pagamentos',
            'contas.ajustes',
            'contas.errosPagamentos'
        ]);
    }
}
