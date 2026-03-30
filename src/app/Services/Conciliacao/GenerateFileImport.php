<?php

namespace App\Services\Conciliacao;

use App\Models\Conciliacao;
use App\Models\Pagamento;
use Carbon\Carbon;

class GenerateFileImport
{

    public static function execute(string $distribuicao, Conciliacao $conciliacao): string
    {
        $pagamentosComJuros = $conciliacao->contas()
            ->with(['pagamentos' => fn($q) => $q->where('tipo', 'com juros')])
            ->get()
            ->flatMap(fn($conta) => $conta->pagamentos);

        $headers = ['data', 'codigo_a', 'numero_conta', 'valor', 'codigo_c', 'numero_nota'];

        $linhas = [];
        $linhas[] = implode(',', $headers);

        foreach ($pagamentosComJuros as $pag) {

            $nota = $pag->conta->notas()
                ->where('numero', $pag->numero_nota)
                ->first();

            $parcelas = self::distribuirDiferenca(distribuicao: $distribuicao, pagamento: $pag);

            $colNota = [
                Carbon::parse($nota->data)->format('d/m/Y'),
                $pag->code,
                $nota->conta->numero,
                $nota?->valor,
                'AAAA',
                $nota->numero
            ];
            $linhas[] = implode(',', array_map(fn($v) => "\"{$v}\"", $colNota));

            foreach($parcelas as $parcela) {
                $colPag = [
                    Carbon::parse($pag->data)->format('d/m/Y'),
                    $pag->code,
                    $pag->conta->numero,
                    $parcela,
                    'XXXX', //$pag->codigo_c,
                    $pag->numero_nota
                ];
                $linhas[] = implode(',', array_map(fn($v) => "\"{$v}\"", $colPag));
            }

        }

        $csvString = implode(PHP_EOL, $linhas);

        return $csvString;
    }

    private static function distribuirDiferenca(string $distribuicao, Pagamento $pagamento)
    {
        if ($pagamento->tipo !== 'com juros') {
            throw new \InvalidArgumentException('Pagamento deve ser do tipo "com juros".');
        }

        $total = $pagamento->valor_nota + $pagamento->valor_juros;
        $quantidadeParcelas = $pagamento->parcela;

        // Valor base (com arredondamento para 2 casas)
        $valorBase = round($total / $quantidadeParcelas, 2);
        $parcelas = array_fill(0, $quantidadeParcelas, $valorBase);

        // Corrige a diferença de arredondamento
        $somaAtual = array_sum($parcelas);
        $diferenca = round($total - $somaAtual, 2); // pode ser 0.01 ou -0.01

        if ($diferenca !== 0.00) {
            switch ($distribuicao) {
                case 'primeira':
                    $parcelas[0] += $diferenca;
                    break;

                case 'ultima':
                    $parcelas[$quantidadeParcelas - 1] += $diferenca;
                    break;

                default:
                    throw new \InvalidArgumentException('Distribuição inválida.');
            }
        }

        return $parcelas;
    }
}
