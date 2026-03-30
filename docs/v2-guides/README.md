# V2 — Diretrizes (API) e Especificação de Upload

Este documento orienta o desenvolvimento da V2 (API) e instrui um agente a entender o comportamento da V1.

## 1. Arquitetura alvo

- V2 deve ser uma API RESTful versionada (`/api/v2/...`).
- Backend e Frontend serão projetos distintos (repositórios separados).
- Autenticação: token-based (OAuth2 / JWT / Personal Access Tokens).
- Processamento: assíncrono via filas (Jobs). O upload retorna 202 Accepted e um `job_id`.

## 2. Responsabilidades

- Backend: receber uploads, enfileirar processamento, executar `Auditar` (Job), persistir via `Store`, expor endpoints para correção manual, gerar exportações e fornecer status/progress.
- Frontend: UI para upload/progresso/edição/seleção/download, consumindo apenas a API.

## 3. Endpoints mínimos sugeridos

- `POST /api/v2/conciliacoes` — criar conciliação (metadados) → retorna `conciliacao_id`.
- `POST /api/v2/conciliacoes/{id}/upload` — upload de arquivo (multipart); retorna 202 + `job_id`.
- `GET  /api/v2/conciliacoes/{id}/status` — status e estatísticas.
- `GET  /api/v2/conciliacoes/{id}` — dados persistidos (notas, pagamentos, problemas).
- `POST /api/v2/conciliacoes/{id}/correcoes` — aplicar correções manuais.
- `POST /api/v2/conciliacoes/{id}/exportacao` — gerar exportação; retorna `arquivo_exportacao_id`.
- `GET  /api/v2/conciliacoes/{id}/exportacao/{export_id}` — obter arquivo.
- `GET  /api/v2/empresas/{id}/config` — obter regras/config da empresa.

## 4. Instruções para o agente entender a V1

- Ler `ANALISE_SISTEMA.md` (especial atenção: serviços `Auditar`, `Store`, `GenerateFileImport`, fluxos do wizard e pontos críticos).
- Identificar e adaptar a lógica central (`Auditar::execute` e `Store::execute`) como serviços reusáveis no backend da V2.
- Mapear ações do Livewire/Filament para chamadas API (ex.: seleção de contas → persistir seleção via endpoint em vez de sessão PHP).
- Tornar o parser (palavras-chave, regex, mapeamento de colunas) configurável por empresa e extraí-lo para um componente reutilizável.
- Corrigir comportamentos problemáticos listados em `9.*` (ex.: deleção total no reupload; processamento síncrono).

## 5. Especificação mínima obrigatória do arquivo de upload

> O documento V1 atual não fornece informação suficiente. O agente/developer deve exigir a documentação abaixo antes de implementar o parser da V2.

Campos e regras mínimas a fornecer:

- `encoding`: ex.: `UTF-8` (indicar se aceita `ISO-8859-1`).
- `delimitador`: `\t` para TXT, `,` para CSV; especificar por formato.
- `presença_header`: booleano (ex.: `true`/`false`).
- `formato_data`: `DD/MM/YYYY` (ex.: `05/03/2026`).
- `separador_decimal`: `.` ou `,` (ex.: `1000,00`).
- `blocos_de_conta`: padrão que indica início de conta (ex.: linha que começa com `Conta: {numero} - {nome}`).
- `colunas_transacao` (índices esperados após split por delimitador):
  - 0 => `data`
  - 1 => `historico`
  - 2 => `codigo` (opcional)
  - 3 => `documento`
  - 4 => `debito`
  - 5 => `credito`
  - 6 => `saldo_banco` (opcional)
- `linha_saldo_anterior`: substring/posição que determina o saldo anterior (ex.: `Saldo Anterior`).
- `palavras_chave_do_parser`: lista completa de palavras que o parser deve reconhecer e o comportamento esperado para cada uma.
- `tratamento_de_colunas_vazias` e `normalizacao_de_numero_nota`.
- Exemplos canônicos (arquivos reais) em `docs/v2-guides/samples/` para TXT, CSV e XLSX.
- JSON Schema ou mapeamento que traduza índices → nomes para o parser.

### Exemplos (mínimos)

TXT (tab):
```
Conta: 12345 - Caixa Geral
05/03/2026	NOTA FISCAL 2026/001		NF001	0,00	1000,00	1000,00
10/03/2026	PAGAMENTO NOTA FISCAL		DOC0001	1000,00	0,00	1000,00
Saldo Anterior						0,00
```

CSV (vírgula):
```
"data","historico","codigo","documento","debito","credito","saldo_banco"
"05/03/2026","NOTA FISCAL 2026/001","","NF001","0,00","1000,00","1000,00"
```

## 6. Checklist de entrega (para o time/agent)

- [ ] Criar `docs/v2-guides/upload-spec.md` com o checklist completo e exemplos reais.
- [ ] Implementar endpoints da API e jobs assíncronos para processamento.
- [ ] Prever webhook/streaming para atualizar frontend durante o processamento.
- [ ] Adicionar migração para enriquecer `arquivos_exportacoes` (path, status, gerado_por).

---

*Fim do guia V2 — criado automaticamente.*
