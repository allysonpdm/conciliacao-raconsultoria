# Services — Documentação Técnica

Este documento descreve os services existentes na versão 1 e seus contratos para a versão 2.

## Visão Geral

Services principais:

- `ConciliacaoService` — orquestrador: valida entrada, chama `Auditar`, persiste com `Store`, gera relatórios e arquivos de exportação.
- `Auditar` — parser e classificadora: transforma arquivo do balanço em estrutura de contas/notas/pagamentos/problemas.
- `Store` — persistência: grava conciliação e relações no banco em lotes/`upsert`.
- `GenerateFileImport` — gera CSV de importação a partir de pagamentos classificados (ex.: `com juros`).
- `Consultar\Empresa` — consulta dados de CNPJ via ReceitaWS (cache recomendado).

Cada service deve ter um README local (`app/Services/.../README.md`) com entradas/saídas e exemplos de uso.

---

## Contracts e Exemplos de Uso

### Auditar::execute(string $diskPath, float $discountThreshold = 0.1, int $monthsToConsiderClosed = 1, array $ignoreAccounts = []): array

- Entrada:
  - `diskPath`: caminho relativo no disco `uploads` (string).
  - `discountThreshold`: limiar para classificar notas como `desconto_paga` (decimal).
  - `monthsToConsiderClosed`: meses para considerar nota fechada (int).
  - `ignoreAccounts`: array de números de conta a ignorar (array<string>).

- Saída: `array` com uma entrada por conta, cada uma contendo:
  - `account`: string ("12345 - Nome")
  - `totals`: [saldo_anterior, saldo_corrente, total_debitos, total_creditos, saldo_banco]
  - `notas`: array de notas com `numero`, `data`, `valor_total`, `valor_pago`, `tipo`, `pagamentos` (lista)
  - `problemas`: ['sem_nota' => [...], 'pagamento_numeracao_errada' => [...], 'com_juros' => [...]]
  - `balanceado`: bool

- Erros/Exceções:
  - Falha de leitura do arquivo (IOException)
  - Memória insuficiente ao processar Excel (throw ou return de erro)

- Observações:
  - Para arquivos XLSX, `memory_limit` é temporariamente elevado (p.ex. 512M) apenas durante a leitura.

Exemplo:
```php
$results = Auditar::execute('user/1/arquivo.xlsx', 0.1, 3);
```

---

### Store::execute(array $auditData, Empresa $empresa): void

- Entrada:
  - `auditData`: saída de `Auditar::execute()`.
  - `empresa`: instância de `Empresa` Eloquent.

- Comportamento:
  - `updateOrCreate` na `conciliacoes` da `empresa`.
  - Remove (delete) as `contas_conciliadas` existentes da conciliação antes de inserir novo dataset.
  - Insere `notas` e `ajustes` em lotes (`DB_BATCH_SIZE`, default 1000).
  - `upsert` pagamentos por `(conta_conciliada_id, doc)`.
  - Persiste `erros_pagamentos` como registros separados.

- Efeitos colaterais:
  - Dados prévios da conciliação são perdidos (recomendar versionamento ou soft-delete se necessário).

Exemplo:
```php
$store = new Store();
$store->execute($results, $empresa);
```

---

### GenerateFileImport::execute(string $distribuicao, Conciliacao $conciliacao): string

- Entrada:
  - `distribuicao`: `primeira` ou `ultima` — onde aplicar diferença de centavos.
  - `conciliacao`: modelo Eloquent com relações carregadas (contas -> pagamentos do tipo "com juros").

- Saída: `string` CSV pronto para escrita/download.

- Observações:
  - Gera primeira a linha da nota e depois linhas por parcela com valores distribuídos.

Exemplo:
```php
$csv = GenerateFileImport::execute('ultima', $conciliacao);
file_put_contents(storage_path('app/exports/conc_'.$conciliacao->id.'.csv'), $csv);
```

---

### ConciliacaoService::store(Empresa $empresa, string $pathFile): void

- Orquestra:
  - Valida entrada e permissões.
  - Chama `Auditar::execute()`.
  - Chama `Store::execute()`.
  - Retorna status ou lança exceção em caso de erro.

- Recomendação para v2 (API): tornar este fluxo assíncrono via Job/Queue:
  - `Upload` → cria `Conciliacao` com `file` e `status: processing`.
  - Disparar `ProcessarConciliacaoJob` (queue) que chama `Auditar` + `Store` e atualiza `status` (completed/failed).
  - Expor endpoint `GET /conciliacoes/{id}/status` e websocket/events para notificação.

---

### Consultar\Empresa::execute(string $cnpj): array

- Consulta ReceitaWS: `https://www.receitaws.com.br/v1/cnpj/{cnpj}` com timeout 30s.
- Retornar dados relevantes (nome, atividade, endereço) ou `null` em falha.
- Recomendação: cachear resultado por CNPJ em Redis com TTL (p.ex. 24h).

---

## Observações para versão 2

- Todos os services devem documentar entradas/saídas via `phpdoc` e README locais.
- Implementar contratos de DTO (Data Transfer Objects) para evitar arrays vagos — p.ex. `AuditResult`, `AccountResult`, `InvoiceDTO`, `PaymentDTO`.
- Testes unitários e de integração obrigatórios para `Auditar` e `Store` (mock de arquivos e DB em sqlite-memory).
- Preparar para execução assíncrona: extrair I/O de disco/DB para classes injetáveis para facilitar mocking.
