# Especificação do Arquivo de Upload — V2

Objetivo: padronizar os metadados e o contrato do arquivo de balanço que alimenta o pipeline de conciliação (parser → auditor → store).

Arquivos gerados:
- `upload-schema.json` — JSON Schema para `upload_metadata` (contrato enviado junto ao upload).
- Exemplos canônicos em `docs/files-examples/` (referenciados abaixo).

Campos principais do `upload_metadata` (resumo)

- `format` (string, enum): `txt`, `csv`, `xlsx`.
- `encoding` (string): ex.: `UTF-8`, `ISO-8859-1`. Pode ser `auto` para detecção.
- `delimiter` (string): `\t`, `,` etc. Para XLSX, pode ser omitido.
- `has_header` (boolean): o arquivo contém header na primeira linha.
- `date_format` (string): ex.: `DD/MM/YYYY`.
- `decimal_separator` (string|enum): `.`, `,` ou `auto`.
- `thousand_separator` (string|null): separador de milhares (opcional).
- `account_block_pattern` (string/regex): padrão que inicia um bloco de conta (ex.: `^Conta:\s*(\d+)\s*-`).
- `saldo_anterior_identifier` (string): substring que identifica a linha de saldo anterior (ex.: `Saldo Anterior`).
- `columns_map` (object): mapeamento índice→nome (0..N) com nomes esperados: `data`, `historico`, `codigo`, `documento`, `debito`, `credito`, `saldo_banco`.
- `keywords` (object): mapa `palavra_chave` → `action` (ex.: `"NOTA FISCAL": "invoice"`).
- `normalize_note_number` (object): regras/regex para normalização de número de nota (remover prefixos, zeros, whitespace).
- `ignore_empty_lines` (boolean) e `trim_fields` (boolean).
- `sample_id` (string, opcional): referência a um arquivo em `docs/files-examples/` para teste e validação.

Heurísticas e validações recomendadas

- Encoding: aceitar `upload_metadata.encoding` quando informado; se `auto`, detectar BOM e usar heurística (chardet/mb_detect_encoding). Em caso de ambiguidade, retornar preview e solicitar confirmação do encoding.
- Decimal separator: `auto` por padrão — inferir a partir da amostra (contagem de `,` vs `.` em campos numéricos). Quando ambíguo, exigir `decimal_separator` explícito.
- Linha válida de transação: regex `^\d{1,2}\/\d{1,2}\/\d{4}` (configurável via `columns_map` e `date_format`).
- Se `has_header=true`, validar presença de colunas esperadas; se faltar, retornar 422 com preview.

Protocolo de upload (recomendado)

1. `POST /api/v2/conciliacoes` → cria recurso meta e retorna `conciliacao_id` (201).
2. `POST /api/v2/conciliacoes/{id}/upload` — multipart: `file`, `upload_metadata` (JSON). Retornar 202 + `{ job_id }` para processamento assíncrono.
3. Disponibilizar endpoint `GET /api/v2/jobs/{job_id}` e `GET /api/v2/conciliacoes/{id}/status`.

Exemplos de `upload_metadata`

1) TXT (tab) — caso típico

```
{
  "format": "txt",
  "encoding": "UTF-8",
  "delimiter": "\t",
  "has_header": false,
  "date_format": "DD/MM/YYYY",
  "decimal_separator": "auto",
  "account_block_pattern": "^Conta:\\s*(\\d+)\\s*-\\s*(.+)$",
  "saldo_anterior_identifier": "Saldo Anterior",
  "columns_map": {"0":"data","1":"historico","2":"codigo","3":"documento","4":"debito","5":"credito","6":"saldo_banco"},
  "keywords": {"NOTA FISCAL":"invoice","PAGAMENTO NOTA FISCAL":"payment","PAGAMENTO DUPLICATA":"payment_dup"},
  "sample_id": "ALLYSON.csv",
  "ignore_empty_lines": true,
  "trim_fields": true
}
```

2) CSV com cabeçalho

```
{
  "format": "csv",
  "encoding": "ISO-8859-1",
  "delimiter": ",",
  "has_header": true,
  "date_format": "DD/MM/YYYY",
  "decimal_separator": ",",
  "columns_map": {"0":"data","1":"historico","2":"codigo","3":"documento","4":"debito","5":"credito","6":"saldo_banco"},
  "sample_id": "SAUDE GENERICA.csv"
}
```

3) XLSX (metadata simplificado)

```
{
  "format": "xlsx",
  "encoding": "auto",
  "has_header": false,
  "date_format": "DD/MM/YYYY",
  "decimal_separator": "auto",
  "columns_map": {"0":"data","1":"historico","2":"codigo","3":"documento","4":"debito","5":"credito","6":"saldo_banco"},
  "sample_id": "D.JESUS FORNECEDORES.csv"
}
```

Samples canônicos (colocar/normalizar em `docs/files-examples/`)
- `ALLYSON.csv` — caso regular
- `D.JESUS FORNECEDORES.csv` — exemplo com vírgula decimal
- `SAUDE GENERICA.csv` — exemplos com linhas vazias e campos deslocados

Checklist para integração do parser

- Implementar validações do `upload_metadata` antes de enfileirar o job.
- Gerar preview (primeiras N linhas) com interpretação do encoding/decimal; em caso de inconsistência, abortar com 422 e preview.
- Testar parser com os 3 samples canônicos e com arquivos grandes (>50k linhas).
- Registrar métricas de parsing (tempo, linhas, erros detectados).

Próximo passo sugerido: gerar `docs/v2-guides/openapi-minimal.yml` com os endpoints `conciliacoes`, `upload` e `jobs`, e adicionar os samples CSV/TXT/XLSX na pasta `docs/files-examples/`.

---
*Fim da especificação de upload (resumo).* 
