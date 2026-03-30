<?php

namespace App\Services\Consultar;

use App\ObjectValues\Cnpj;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Empresa
{
    /**
     * Consulta os dados da empresa via API ReceitaWS
     *
     * @param string|Cnpj $cnpj
     * @return array|null
     */
    public function consultarCnpj(string|Cnpj $cnpj): ?array
    {
        $cnpj = new Cnpj((string) $cnpj);
        $cnpjValue = $cnpj->getValue();

        try {
            $response = Http::timeout(30)->get("https://www.receitaws.com.br/v1/cnpj/{$cnpjValue}");

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'ERROR') {
                    throw new \RuntimeException($data['message'] ?? 'Erro na consulta da API.');
                }

                return $data;
            } else {
                throw new \RuntimeException('Falha na requisição para a API ReceitaWS.');
            }
        } catch (\Exception $e) {
            Log::error('Erro ao consultar CNPJ: ' . $e->getMessage());
            throw $e;
        }
    }
}
