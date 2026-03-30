# Arquitetura V2 — API (Backend) + Frontend Separados

Objetivo: orientar o agente IA (ou equipe) que implementará a versão 2 com backend API RESTful e frontend separado (SPA). Fornece contratos, rotas, jobs assíncronos, autenticação e responsabilidades.

---

## Visão Geral

- Backend: API RESTful Laravel (ou Lumen) servindo JSON com autenticação via JWT/OAuth2.
- Frontend: SPA (React ou Vue 3 + Pinia/Vuex) consumindo a API; implementa o wizard de conciliação no cliente.
- Processamento de arquivos: upload + processamento assíncrono via Jobs/Queues (Redis) e notificações via WebSockets (Pusher/laravel-websockets).
- Armazenamento de arquivos: `uploads` (S3 compatível ou disco local durante testes).
- CI/CD: pipelines para backend e frontend separados.

---

## Principais Recursos/Endpoints (REST)

### Autenticação

- `POST /api/auth/login` — body: `{ email, password }` → returns `{ access_token, refresh_token, user }`.
- `POST /api/auth/refresh` — refresh token.
- `POST /api/auth/logout` — revogar token.

### Empresas

- `GET /api/empresas` — lista paginada de empresas do usuário autenticado.
- `POST /api/empresas` — criar empresa (body inclui CNPJ, nome, conta_conciliacao e config inicial).
- `GET /api/empresas/{id}` — obter detalhe (autorização: pertence ao usuário).
- `PUT /api/empresas/{id}` — atualizar.
- `DELETE /api/empresas/{id}` — remover.

### Conciliações

- `POST /api/empresas/{empresa_id}/conciliacoes` — cria nova conciliação e inicia upload (multipart file) — retorna conciliacao resource com `id` e `status: processing`.
- `GET /api/conciliacoes/{id}` — obter estado e metadados (conta counts, porcentagens, status).
- `GET /api/conciliacoes/{id}/status` — retorno simples `{ status: processing|completed|failed, progress: 0-100 }`.
- `GET /api/conciliacoes/{id}/download-export` — baixar CSV gerado (autorização + rate limit).

### Contas, Notas, Pagamentos

- `GET /api/conciliacoes/{id}/contas` — lista de `contas_conciliadas` (filtros: balanceado)
- `GET /api/contas/{conta_id}/notas` — notas da conta (filtros: tipo, periodo)
- `GET /api/contas/{conta_id}/pagamentos` — pagamentos da conta (filtros: tipo, periodo)
- `POST /api/conciliacoes/{id}/contas/selecoes` — salvar seleção de contas para exportação (persistir em DB em vez de sessão)
- `POST /api/notas/marcar-para-export` — body `{ conciliacao_id, nota_ids[] }`
- `POST /api/pagamentos/marcar-para-export` — body `{ conciliacao_id, pagamento_ids[] }`

### Erros de Pagamento

- `GET /api/conciliacoes/{id}/erros` — lista `erros_pagamentos` com sugestão.
- `PUT /api/erros/{erro_id}` — atualizar `sugestao_numero_nota` (correção manual).

### Jobs / Webhooks

- `POST /api/conciliacoes/{id}/process` — (interno) enfileira `ProcessarConciliacaoJob` (opcional).
- Websocket events:
  - `conciliacao.{id}.progress` — { progress }
  - `conciliacao.{id}.completed` — { conciliacao_id }
  - `conciliacao.{id}.failed` — { error }

---

## Contratos JSON (Exemplos)

### Resposta de criação de conciliação (upload)

```json
{
  "id": 123,
  "empresa_id": 45,
  "status": "processing",
  "file": "user/45/arquivo.xlsx",
  "created_at": "2026-03-30T10:00:00Z"
}
```

### Status polling

```json
{
  "status": "processing",
  "progress": 42
}
```

### Resultado resumido (GET /api/conciliacoes/{id})

```json
{
  "id": 123,
  "empresa_id": 45,
  "stats": {
    "contas": 120,
    "pagamentos": 4293,
    "notas": 7539,
    "ajustes": 12,
    "erros": 10
  },
  "updated_at": "2026-03-30T10:12:00Z"
}
```

---

## Processamento Assíncrono (Recomendado)

1. Upload do arquivo → criar `conciliacoes` com `status = processing`.
2. Disparar `ProcessarConciliacaoJob` na fila (Redis): job executa `Auditar::execute()` → grava com `Store::execute()`.
3. Job atualiza progresso (usar um canal progressivo no cache / DB) e emite eventos websocket.
4. Quando finalizado, `status = completed` e o arquivo de exportação é gerado e referenciado em `arquivos_exportacoes`.
5. Frontend escuta eventos e permite download.

---

## Autenticação e Segurança

- Usar JWT (Laravel Sanctum ou Passport) com refresh tokens.
- Autorizações: `EmpresaPolicy`, `ConciliacaoPolicy` para garantir que recursos pertençam ao usuário.
- Rate limit para endpoints de upload e download.
- Validação estrita de arquivos (tipos, tamanho) no backend e no frontend.

---

## Responsabilidades do Frontend (SPA)

- Implementar o wizard (upload → visão geral → selecionar contas → corrigir erros → notas → pagamentos → exportação).
- Realizar upload via `POST /api/empresas/{id}/conciliacoes` (multipart).
- Polling ou websockets para acompanhar `status`/`progress` da conciliação.
- Manter seleção local para UX, mas persistir seleção no backend via `POST /api/conciliacoes/{id}/contas/selecoes`.
- Exibir tabelas com paginação; usar chamadas paginadas para evitar carga massiva (server-side pagination).

---

## Migração dos ObjectValues e Schema

- Backend deve expor APIs que aceitem tanto formas _sanitizadas_ quanto _mascaradas_ (p.ex. CNPJ com ou sem máscara) — o backend deve sanitizar e validar.
- ObjectValues devem ser disponibilizados como pacote/namespace reutilizável no backend e, quando relevante, oferecer utilitários no frontend (p.ex. máscaras via lib JS).

---

## Observações para o Agente IA que Implementará a V2

- Primeiro passo: scaffolding do monorepo com `backend/` e `frontend/`.
- Implementar autenticação e policies antes de endpoints de domínio.
- Priorizar pipeline: upload assíncrono → processamento em fila → notificações → download de exportação.
- Fornecer mocks e fixtures (ex.: arquivos de balanço) para testes automatizados do pipeline.

---

*Fim da especificação de arquitetura V2.*
