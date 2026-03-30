# Análise Completa do Sistema — Conciliação RA Consultoria

> **Documento gerado por:** GitHub Copilot (Arquiteto de Software Sênior)  
> **Data:** 2025  
> **Versão:** 1.0  

---

## Sumário

1. [Visão Geral do Sistema](#1-visão-geral-do-sistema)
2. [Regras de Negócio](#2-regras-de-negócio)
3. [Modelagem Atual do Banco de Dados](#3-modelagem-atual-do-banco-de-dados)
4. [Arquitetura e Estrutura do Projeto](#4-arquitetura-e-estrutura-do-projeto)
5. [Services e Camadas de Negócio](#5-services-e-camadas-de-negócio)
6. [Fluxos do Sistema](#6-fluxos-do-sistema)
7. [Entrada e Saída de Dados](#7-entrada-e-saída-de-dados)
8. [Relatórios e Processamentos](#8-relatórios-e-processamentos)
9. [Pontos Críticos](#9-pontos-críticos)

---

## 1. Visão Geral do Sistema

### 1.1 Propósito

O sistema **Conciliação RA Consultoria** é uma aplicação web SaaS de **conciliação contábil automatizada**. Seu objetivo é processar o arquivo de balanço contábil de uma empresa (exportado do sistema de gestão contábil em formato TXT/CSV/XLSX), auditar as transações contra as notas fiscais emitidas, classificar os pagamentos e notas fiscais quanto ao seu status, identificar possíveis erros humanos de digitação, e gerar um arquivo de importação formatado para lançamentos contábeis subsequentes.

### 1.2 Público-alvo

Contadores e escritórios de contabilidade que gerenciam múltiplas empresas clientes e precisam realizar a conciliação contábil periodicamente.

### 1.3 Stack Tecnológica

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| Linguagem | PHP | ^8.2 |
| Framework Web | Laravel | ^12.0 |
| Admin Panel | Filament | ^4.0 |
| Componentes Reativos | Livewire | ^3.0 (via Filament) |
| JavaScript Reativo | Alpine.js | (via Filament) |
| Banco de Dados | MySQL | 8.x |
| Cache / Queue | Redis | (via Docker) |
| Parser Excel/CSV | Maatwebsite/Excel | ^3.1 |
| Geração PDF | Knp\Snappy (wkhtmltopdf) | ^1.5 |
| Auditoria de Dados | OwenIt Laravel Auditing | ^14.0 |
| Validação de Objetos | Symfony Validator | ^7.3 |
| Containerização | Docker (nginx + php-fpm + mysql + redis + php-worker) | — |
| API Externa | ReceitaWS (CNPJ) | — |

### 1.4 Ambiente de Execução

O sistema roda integralmente via Docker Compose com quatro serviços:

- **`balanco-php-fpm`**: PHP-FPM com a aplicação Laravel.
- **`balanco-nginx`**: Proxy reverso nginx servindo as requisições HTTP/HTTPS.
- **`balanco-mysql`**: Banco de dados MySQL com scripts de inicialização.
- **`balanco-redis`**: Cache e fila de jobs.
- **`balanco-php-worker`**: Worker Supervisord para processamento de filas.

O artisan reside em `/var/www/artisan` dentro do container.

### 1.5 Painel Administrativo (Filament)

O painel opera sob o prefixo de rota `filament.conciliacao.*` com autenticação nativa do Laravel. Toda a interface do usuário é construída com Filament v4, usando Livewire 3 para os componentes interativos dentro do wizard de conciliação.

---

## 2. Regras de Negócio

### 2.1 Multitenancy por Usuário

- Cada `User` pode cadastrar múltiplas `Empresa`s.
- Cada empresa pertence a **um único usuário**: a combinação `(user_id, cnpj)` e `(user_id, conta_conciliacao)` são únicas no banco.
- Um usuário **não vê dados de outros usuários** (isolamento via `user_id`).

### 2.2 Cadastro de Empresa

- O CNPJ é **validado matematicamente** (dois dígitos verificadores) via `CnpjValidationRule` e pelo Object Value `Cnpj`, que usa `Symfony\Validator\Constraints\Cnpj`.
- O CNPJ é **sempre armazenado em formato numérico puro** (14 dígitos, sem máscara), exibido mascarado no formato `XX.XXX.XXX/XXXX-XX`.
- O `conta_conciliacao` é único por usuário (regra `UniqueContaConciliacaoForUser`).
- Ao criar uma empresa, uma `Conciliacao` vazia é automaticamente criada via `handleRecordCreation()`.
- Dados adicionais de CNPJ podem ser consultados via **ReceitaWS** (`https://www.receitaws.com.br/v1/cnpj/{cnpj}`, timeout 30s).

### 2.3 Configuração por Empresa

Cada empresa possui um `Config` (1:1) com os seguintes parâmetros que **controlam o algoritmo de auditoria**:

| Parâmetro | Descrição | Padrão |
|-----------|-----------|--------|
| `conta_juros` | Conta contábil dos juros | — |
| `conta_descontos` | Conta contábil dos descontos | — |
| `conta_pagamentos` | Conta da origem do pagamento (caixa/banco) | — |
| `codigo_historico_juros` | Código de histórico dos juros | — |
| `codigo_historico_descontos` | Código de histórico dos descontos | — |
| `codigo_historico_pagamentos` | Código de histórico dos pagamentos | — |
| `percentual_min_pago` | % mínimo pago para considerar paga com desconto | 85% |
| `meses_tolerancia_desconto` | Meses de tolerância para aceitar desconto após % mín. | 1 |
| `meses_tolerancia_sem_pagamentos` | Meses sem pagamento para classificar como `nao_paga` | 6 |
| `parcelar` | Habilita parcelamento automático dos pagamentos de juros | true |
| `valor_minimo_parcela` | Valor mínimo para acionar o parcelamento | — |
| `valor_maximo_parcela` | Valor máximo por parcela (mutuamente exclusivo com `numero_maximo_parcelas`) | — |
| `numero_maximo_parcelas` | Qtd. máxima de parcelas (mutuamente exclusivo com `valor_maximo_parcela`) | 3 |
| `inicio_periodo_pagamento` | Início do intervalo (em dias após NF) para datas automáticas de pagamento | 15 |
| `fim_periodo_pagamento` | Fim do intervalo (em dias após NF) para datas automáticas de pagamento | 20 |
| `parcela_preferencial_get` | Qual parcela usar para obter data quando há múltiplas (`first`/`last`) | `last` |
| `parcela_preferencial_set` | Qual parcela ajustar para diferença de centavos (`first`/`last`) | `last` |

### 2.4 Tipos de Nota Fiscal (`notas.tipo`)

O algoritmo de auditoria classifica cada nota em **cinco estados exclusivos**:

| Tipo | Descrição |
|------|-----------|
| `paga` | Nota totalmente paga (diferença ≤ `TOLERANCE=0.01`) |
| `nao_paga` | Nota sem qualquer pagamento ou com pagamento muito antigo (`> meses_tolerancia_sem_pagamentos`) |
| `parcialmente_paga` | Nota com pagamentos, mas insuficientes para cobrira pelo % mínimo |
| `desconto_paga` | Nota cujo valor pago está abaixo do total, mas acima do `percentual_min_pago` e dentro da tolerância de meses |
| `com_juros_paga` | Nota cujo valor pago supera o total da nota (juros incidentes) |

### 2.5 Tipos de Pagamento (`pagamentos.tipo`)

| Tipo | Descrição |
|------|-----------|
| `pago com nota` | Pagamento associado corretamente a uma nota fiscal existente |
| `nota não encontrada` | Pagamento sem nota fiscal correspondente no balanço |
| `com juros` | Pagamento cujo valor supera o valor da nota (juros cobrados) |
| `com descontos` | Pagamento cujo valor está abaixo da nota (desconto obtido) |
| `parcialmente pago` | Pagamento parcial de uma nota |
| `anterior` | Pagamento identificado como ajuste anterior ao período analisado |

### 2.6 Classificação de Ajustes Anteriores (`ajustes_anteriores.tipo`)

| Tipo | Descrição |
|------|-----------|
| `D` | Débito — lançamento a débito na conta |
| `C` | Crédito — lançamento a crédito na conta |

### 2.7 Detecção de Erros Humanos

O algoritmo detecta pagamentos com **número de nota digitado incorretamente** usando dois critérios combinados:

1. **Similaridade textual** via `similar_text()` nativo do PHP: se o número informado for > **80%** similar ao número de uma nota existente (`SIMILARITY_THRESHOLD = 80.0`).
2. **Correspondência de valor**: se o valor pago corresponde (tolerância `0.01`) ao valor da nota candidata **e** o número candidato é mais longo (mais específico) que o digitado.

Os erros detectados são salvos em `erros_pagamentos` com:
- `numero_nota`: o número original (provavelmente errado).
- `sugestao_numero_nota`: a nota candidata sugerida pelo algoritmo.
- O contador pode **corrigir manualmente** a sugestão na **Etapa 4** do wizard.

### 2.8 Status de Balanceamento de Contas (`contas_conciliadas.balanceado`)

Uma conta é marcada como `balanceado = true` quando o saldo calculado internamente após todos os lançamentos bate com o `saldo_banco` reportado no arquivo. Contas desbalanceadas indicam divergências que precisam de atenção.

### 2.9 Geração do Arquivo de Importação

O arquivo de importação gerado é um **CSV** contendo os lançamentos dos pagamentos do tipo `com juros`, com as parcelas distribuídas conforme `parcela_preferencial_set`:
- A diferença de centavos oriunda do arredondamento é ajustada na primeira ou última parcela.
- Cada pagamento gera múltiplas linhas (uma por parcela + uma para a nota).
- Colunas: `data`, `codigo_a`, `numero_conta`, `valor`, `codigo_c`, `numero_nota`.

### 2.10 Seleção de Contas para Exportação

No wizard de conciliação:
- O usuário seleciona quais contas conciliadas deseja exportar (**Etapa 3**).
- Os IDs das contas selecionadas são salvos em **sessão PHP** com a chave `contas_para_conciliar_{conciliacaoId}`.
- As etapas 4, 5 e 6 filtram seus dados usando essa chave de sessão.
- As notas/pagamentos marcados para exportação nas etapas 5/6 são salvos nas chaves `notas_para_exportar_{id}` e `pagamentos_para_exportar_{id}`.

---

## 3. Modelagem Atual do Banco de Dados

### 3.1 Diagrama de Relacionamentos (ERD Textual)

```
users
  └──< empresas (user_id)
         ├──1 configs (empresa_id)
         └──1 conciliacoes (empresa_id)
                ├──1 arquivos_exportacoes (conciliacao_id)
                └──< contas_conciliadas (conciliacao_id)
                       ├──< notas (conta_conciliada_id)
                       ├──< pagamentos (conta_conciliada_id)
                       ├──< ajustes_anteriores (conta_conciliada_id)
                       └──< erros_pagamentos (conta_conciliada_id)
```

### 3.2 Tabela `users`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | auto-increment |
| `name` | string | — |
| `email` | string unique | — |
| `email_verified_at` | timestamp | nullable |
| `password` | string (hashed) | — |
| `remember_token` | string | nullable |
| `created_at` / `updated_at` | timestamps | — |

### 3.3 Tabela `empresas`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `user_id` | FK → users | cascade delete |
| `nome` | string(100) | indexed |
| `cnpj` | string(14) | numérico puro, indexed |
| `conta_conciliacao` | string(10) | nullable, indexed |
| `created_at` / `updated_at` / `deleted_at` | timestamps | soft delete manual |
| — | UNIQUE | `(user_id, cnpj)` |
| — | UNIQUE | `(user_id, conta_conciliacao)` |

### 3.4 Tabela `configs`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `empresa_id` | FK unique → empresas | cascade delete, 1:1 |
| `conta_juros` | string | indexed |
| `conta_descontos` | string | indexed |
| `conta_pagamentos` | string | indexed |
| `codigo_historico_juros` | string | indexed |
| `codigo_historico_descontos` | string | indexed |
| `codigo_historico_pagamentos` | string | indexed |
| `parcela_preferencial_set` | enum('first','last') | default 'last' |
| `parcela_preferencial_get` | enum('first','last') | default 'last' |
| `percentual_min_pago` | decimal(5,2) | default 85.00 |
| `meses_tolerancia_desconto` | smallint | default 1 |
| `meses_tolerancia_sem_pagamentos` | smallint | default 6 |
| `parcelar` | boolean | default true |
| `valor_minimo_parcela` | decimal(15,2) | nullable |
| `valor_maximo_parcela` | decimal(15,2) | nullable |
| `numero_maximo_parcelas` | smallint | nullable |
| `inicio_periodo_pagamento` | smallint | nullable |
| `fim_periodo_pagamento` | smallint | nullable |
| `created_at` / `updated_at` / `deleted_at` | timestamps | — |

### 3.5 Tabela `conciliacoes`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `empresa_id` | FK → empresas | cascade delete |
| `file` | string(100) | nullable, caminho no disco `uploads` |
| `created_at` / `updated_at` / `deleted_at` | timestamps | — |

### 3.6 Tabela `contas_conciliadas`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `conciliacao_id` | FK → conciliacoes | cascade delete |
| `numero` | string(10) | indexed |
| `nome` | string(100) | nullable, indexed |
| `mascara_contabil` | string(15) | nullable |
| `balanceado` | boolean | default false |
| `created_at` / `updated_at` / `deleted_at` | timestamps | — |

### 3.7 Tabela `notas`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `conta_conciliada_id` | FK → contas_conciliadas | cascade delete |
| `numero` | string(10) | — |
| `data` | date | — |
| `valor` | decimal(15,2) | valor total da nota |
| `valor_pago` | decimal(15,2) | nullable, total pago |
| `tipo` | enum | paga / nao_paga / parcialmente_paga / desconto_paga / com_juros_paga |
| `created_at` / `updated_at` / `deleted_at` | timestamps | — |

### 3.8 Tabela `pagamentos`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `conta_conciliada_id` | FK → contas_conciliadas | cascade delete |
| `data` | date | data do pagamento |
| `doc` | string(15) | documento |
| `parcela` | smallint unsigned | número da parcela |
| `numero_nota` | string(10) | nullable, nota associada |
| `valor_nota` | decimal(15,2) | nullable |
| `valor_pago` | decimal(15,2) | total pago (inclui juros) |
| `valor_juros` | decimal(15,2) | nullable |
| `valor_descontos` | decimal(15,2) | nullable |
| `code` | string(10) | nullable, código interno |
| `tipo` | enum | anterior / nota não encontrada / com juros / com descontos / parcialmente pago / pago com nota |
| `created_at` / `updated_at` / `deleted_at` | timestamps | — |
| — | UNIQUE | `(conta_conciliada_id, doc)` |

### 3.9 Tabela `ajustes_anteriores`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `conta_conciliada_id` | FK → contas_conciliadas | cascade delete |
| `data` | date | — |
| `doc` | string(15) | — |
| `numero_nota` | string(10) | — |
| `valor` | decimal(15,2) | valor do ajuste |
| `tipo` | enum('D','C') | D=Débito, C=Crédito |
| `created_at` / `updated_at` / `deleted_at` | timestamps | — |

### 3.10 Tabela `erros_pagamentos`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `conta_conciliada_id` | FK → contas_conciliadas | cascade delete |
| `data` | date | data do pagamento |
| `doc` | string(15) | documento |
| `numero_nota` | string(10) | número original (possivelmente errado) |
| `valor_pago` | decimal(15,2) | — |
| `sugestao_numero_nota` | string(10) | sugestão algorítmica ou corrigida manualmente |
| `created_at` / `updated_at` / `deleted_at` | timestamps | — |

### 3.11 Tabela `arquivos_exportacoes`

| Coluna | Tipo | Detalhes |
|--------|------|---------|
| `id` | bigint PK | — |
| `conciliacao_id` | FK unique → conciliacoes | cascade delete, 1:1 |
| `created_at` / `updated_at` | timestamps | — |

> **Observação:** A tabela `arquivos_exportacoes` está estruturada mas parece estar em desenvolvimento — não há colunas de conteúdo (path, status, etc.) além dos timestamps e chave estrangeira.

### 3.12 Tabelas de Infraestrutura

- **`audits`**: Rastreia alterações em todos os modelos auditáveis (Empresa, Config, Conciliacao, ContaConciliada, Nota, Pagamento, Ajuste, ErroPagamento, ArquivoExportacao) via `owen-it/laravel-auditing`.
- **`cache`** e **`jobs`** (Laravel padrão): filas de jobs e cache de sessão/dados.

---

## 4. Arquitetura e Estrutura do Projeto

### 4.1 Visão de Camadas

```
┌──────────────────────────────────────────────┐
│              Filament Admin Panel             │
│  (Resources / Pages / Widgets / Wizard)       │
├──────────────────────────────────────────────┤
│              Livewire Components              │
│  (ConciliacaoOverview, ConciliacaoAjustes,   │
│   CorrigirErros, NotasFiscais, Pagamentos)    │
├──────────────────────────────────────────────┤
│              Services Layer                   │
│  (ConciliacaoService, Auditar, Store,         │
│   GenerateFileImport, Consultar\Empresa)      │
├──────────────────────────────────────────────┤
│              Eloquent Models                  │
│  (User, Empresa, Config, Conciliacao,         │
│   ContaConciliada, Nota, Pagamento,           │
│   Ajuste, ErroPagamento, ArquivoExportacao)   │
├──────────────────────────────────────────────┤
│              MySQL Database                   │
└──────────────────────────────────────────────┘
```

### 4.2 Estrutura de Diretórios

```
src/
├── app/
│   ├── Enums/Brazil/           # StatesEnum (estados brasileiros)
│   ├── Filament/
│   │   ├── Pages/
│   │   │   └── Dashboard.php   # Dashboard Filament (full width)
│   │   └── Resources/
│   │       └── Empresas/
│   │           ├── EmpresaResource.php          # Resource principal
│   │           ├── Components/
│   │           │   ├── Config.php               # Formulário de config (static factory)
│   │           │   └── Empresa.php              # Campos da empresa
│   │           ├── Pages/
│   │           │   ├── CreateEmpresa.php        # Wizard criação (4 passos)
│   │           │   ├── EditEmpresa.php          # Edição simples
│   │           │   ├── ListEmpresas.php         # Listagem
│   │           │   ├── Conciliacao.php          # Página principal do wizard
│   │           │   └── Config.php               # Página de configurações
│   │           ├── Schemas/
│   │           │   └── EmpresaForm.php          # Schema do formulário
│   │           └── Tables/
│   │               └── EmpresasTable.php        # Schema da tabela
│   ├── Http/Controllers/       # (sem controllers customizados — tudo via Filament)
│   ├── Livewire/
│   │   ├── ConciliacaoOverview.php  # Widget StatsOverviewWidget
│   │   ├── ConciliacaoAjustes.php   # Step 3: seleção de contas
│   │   ├── CorrigirErros.php        # Step 4: correção de erros
│   │   ├── NotasFiscais.php         # Step 5: tabela de notas
│   │   └── Pagamentos.php           # Step 6: tabela de pagamentos
│   ├── Models/
│   │   ├── User.php
│   │   ├── Empresa.php
│   │   ├── Config.php
│   │   ├── Conciliacao.php
│   │   ├── ContaConciliada.php
│   │   ├── Nota.php
│   │   ├── Pagamento.php
│   │   ├── Ajuste.php
│   │   ├── ErroPagamento.php
│   │   └── ArquivoExportacao.php
│   ├── ObjectValues/             # Value Objects (Cnpj, Cpf, Decimal, etc.)
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   ├── Rules/                    # Validation Rules customizadas
│   └── Services/
│       ├── ConciliacaoService.php
│       └── Conciliacao/
│           ├── Auditar.php
│           ├── Store.php
│           └── GenerateFileImport.php
│       └── Consultar/
│           └── Empresa.php
├── database/migrations/         # 13 arquivos de migração
├── resources/views/
│   ├── filament/resources/empresas/pages/
│   │   ├── conciliacao.blade.php
│   │   └── config.blade.php
│   ├── livewire/
│   │   ├── conciliacao-ajustes.blade.php
│   │   ├── corrigir-erros.blade.php
│   │   ├── notas-fiscais.blade.php
│   │   └── pagamentos.blade.php
│   └── relatorios/
│       └── balanco.blade.php
└── routes/
    ├── web.php   # Apenas rota raiz (welcome view)
    └── console.php
```

### 4.3 Wizard de Conciliação — Estrutura dos Steps

O wizard usa o componente `Filament\Schemas\Components\Wizard` com a chave `conciliacao-wizard`. Todos os 7 steps são **renderizados no DOM simultaneamente** (visibilidade controlada por CSS com a classe `fi-active`), não por `x-if`.

| Index | Step | Componente | Descrição |
|-------|------|-----------|-----------|
| 0 | Subir o Arquivo | `FileUpload` inline | Upload do arquivo CSV/TXT com processamento automático após upload |
| 1 | Visão Geral | `ConciliacaoOverview` | Dashboard com stats: contas, pagamentos, notas, ajustes, erros, desbalanceadas |
| 2 | Selecionar Contas | `ConciliacaoAjustes` | Tabela selecionável de contas; confirmar grava em sessão |
| 3 | Corrigir Erros | `CorrigirErros` | Tabela de `erros_pagamentos`; edição inline do `sugestao_numero_nota` |
| 4 | Notas Fiscais | `NotasFiscais` | Tabela de notas filtradas pelas contas da sessão, selecionáveis para exportação |
| 5 | Pagamentos sem Nota | `Pagamentos` | Tabela de pagamentos filtrados pelas contas da sessão, selecionáveis |
| 6 | Exportação | — | **Não implementado** |

**Navegação especial:**
- O botão "Próximo" (step index 2) fica **oculto** — a navegação é disparada pelo botão "Confirmar contas" dentro do componente Livewire, que chama `confirmarSelecionadasFromFooterWithIds()` e então chama `requestNextStep()` do Alpine.js.
- Nos steps 4 e 5 (índices do wizard), o botão "Próximo" dispara `marcar-para-exportacao` (CustomEvent) antes de avançar.
- O wizard é `skippable()` se `conciliacao.file` não for nulo.

### 4.4 Coordenação Alpine.js ↔ Livewire

O wizard usa Alpine.js internamente (`wizard.js` minificado). A comunicação entre o botão no componente Livewire e o wizard Alpine funciona assim:

```
[Botão no Blade Livewire]
  ↓  x-on:click
  ↓  coleta IDs dos checkboxes via:
     $el.closest('[wire:id]').querySelectorAll('.fi-ta-record-checkbox:checked')
  ↓  $wire.call('confirmarSelecionadasFromFooterWithIds', ids)
  ↓  [Retorna { hasErrors: bool }]
  ↓  Alpine.$data(el).requestNextStep() ou goToNextStep()
```

> **Crítico**: O escopo do `querySelectorAll` é restrito ao container `[wire:id]` do componente atual para evitar coletar checkboxes de outros steps renderizados no DOM.

### 4.5 Object Values

A camada de **Value Objects** (`app/ObjectValues/`) encapsula tipos de domínio com validação forte:

| Classe | Propósito |
|--------|-----------|
| `Cnpj` | CNPJ com máscara `XX.XXX.XXX/XXXX-XX`, sanitização e validação |
| `Cpf` | CPF com máscara `XXX.XXX.XXX-XX` |
| `CpfCnpj` | Aceita CPF ou CNPJ automaticamente |
| `Decimal` | Número decimal com separador configurável |
| `Email` | E-mail validado |
| `Monetario` | Valor monetário em BRL |
| `Percentual` | Valor percentual |
| `Regex` | Valor genérico com validação por regex |

Todos herdam de `ObjectValue` base e utilizam `Symfony\Validator`.

### 4.6 Validation Rules

| Classe | Propósito |
|--------|-----------|
| `CnpjValidationRule` | Valida CNPJ com cálculo de dígitos verificadores |
| `CpfValidationRule` | Valida CPF com cálculo de dígitos verificadores |
| `UniqueCnpjForUser` | CNPJ único por usuário autenticado |
| `UniqueContaConciliacaoForUser` | Conta conciliação única por usuário |

---

## 5. Services e Camadas de Negócio

### 5.1 `ConciliacaoService`

**Facade** que centraliza todas as operações de conciliação.

```
ConciliacaoService
├── store(Empresa $empresa, string $pathFile): void
│   └── Chama Auditar::execute() → Store::execute()
├── relatorioHtml(Empresa $empresa): string
│   └── Renderiza view('relatorios.balanco') com dados da conciliação
├── relatorioPdf(Empresa $empresa): BinaryFileResponse|StreamedResponse
│   └── Usa Knp\Snappy\Pdf + wkhtmltopdf em /usr/bin/wkhtmltopdf
└── generateImportFile(Conciliacao $conciliacao, string $distribuicao): string
    └── Chama GenerateFileImport::execute()
```

### 5.2 `Auditar`

O **núcleo do algoritmo de conciliação**. Processa o arquivo de balanço e retorna uma estrutura de dados completa.

**Constantes:**

| Constante | Valor | Descrição |
|-----------|-------|-----------|
| `TOLERANCE` | 0.01 | Tolerância para comparação de valores (centavos) |
| `DEFAULT_DISCOUNT_THRESHOLD` | 0.1 (10%) | Limiar padrão de desconto (sobrescrito pela Config) |
| `DEFAULT_MONTHS_TO_CONSIDER_CLOSED` | 1 | Meses padrão para fechar nota |
| `SIMILARITY_THRESHOLD` | 80.0 | % mínimo de similaridade textual para sugerir correção |

**Pipeline de processamento:**

```
Auditar::execute(diskPath, discountThreshold, monthsToConsiderClosed)
  ↓
parseAccounts() — detecta extensão
  ├── TXT/padrão → parseAccountsText() — split por linhas, identifica blocos "Conta:"
  └── CSV/XLSX   → parseAccountsExcelOptimized() — Excel::toArray() com memory_limit=512M
  ↓
Para cada conta:
  processAccount()
    ├── extractPreviousBalance() — busca linha "Saldo Anterior"
    ├── initializeTotals()
    ├── initializeProblems() → { sem_nota[], pagamento_numeracao_errada[], ajustados[], com_juros[] }
    ├── Para cada transação:
    │   ├── isValidTransactionLine() — regex /^\d{1,2}\/\d{1,2}\/\d{4}/
    │   ├── parseTransactionLine() — split por \t → {date, type, code, document, debit, credit, bank_balance}
    │   ├── updateTotals()
    │   ├── processNotes() — detecta NOTA FISCAL (sem PAGAMENTO/DUPLICATA)
    │   ├── processInvoice() — acumula valor_total e data da nota
    │   └── processPayment():
    │       ├── isPaymentTransaction() — contém "PAGAMENTO DUPLICATA", "PAGAMENTO NOTA FISCAL", etc.
    │       ├── Se nota não encontrada → findCandidateNote() (similar_text > 80%)
    │       │   └── → problemas.pagamento_numeracao_errada[]
    │       ├── Se sem nota → problemas.sem_nota[]
    │       └── Se nota existe → processValidPayment()
    │           ├── Se total_pago > valor_nota → problemas.com_juros[]
    │           └── Caso contrário → registra pagamento na nota
    ├── summarizeInvoices() — classifica notas em seus 5 tipos
    └── processPaymentsWithoutNotes() — categoriza sem_nota como 'anterior' ou 'nota não encontrada'
  ↓
Retorna array de contas com todos os dados processados
```

**Formato de saída por conta:**

```php
[
    'account' => 'numero - nome',
    'totals' => [
        'saldo_anterior', 'saldo_corrente',
        'total_debitos', 'total_creditos', 'saldo_banco'
    ],
    'notas' => [...],           // notas classificadas
    'problemas' => [...],       // pagamentos problemáticos
    'balanceado' => bool,       // saldo_corrente == saldo_banco
    // ...
]
```

### 5.3 `Store`

Persiste os resultados do `Auditar` no banco de dados.

**Constante:** `DB_BATCH_SIZE = 1000`

**Fluxo:**

```
Store::execute(array $auditData, Empresa $empresa)
  ↓
updateOrCreateConciliacao() → conciliacoes (upsert)
  ↓
conciliacao->contas()->delete() → limpa TODOS os dados anteriores da conciliação
  ↓
Para cada conta nos dados:
  firstOrCreateConta() → contas_conciliadas
  ↓
  processNotas()        → INSERT em lotes de 1000
  processPagamentos()   → UPSERT keyed em (conta_conciliada_id, doc)
  processAjustes()      → INSERT em lotes de 1000
  processErrosPagamentos() → INSERT em lotes de 1000
```

> **Importante:** A cada novo upload, **todos os dados anteriores da conciliação são deletados** (cascade). Não há versionamento.

### 5.4 `GenerateFileImport`

Gera o CSV de importação apenas para pagamentos do tipo `com juros`.

**Estrutura do CSV:**

```csv
"data","codigo_a","numero_conta","valor","codigo_c","numero_nota"
"dd/mm/yyyy","[code]","[conta_nota]","[valor_nota]","AAAA","[numero_nota]"   ← linha da nota
"dd/mm/yyyy","[code]","[conta_pag]","[parcela_1]","XXXX","[numero_nota]"    ← linha parcela 1
"dd/mm/yyyy","[code]","[conta_pag]","[parcela_2]","XXXX","[numero_nota]"    ← linha parcela 2
```

### 5.5 `Consultar\Empresa`

Consulta dados cadastrais de CNPJ via ReceitaWS:

```
GET https://www.receitaws.com.br/v1/cnpj/{cnpj}
Timeout: 30 segundos
```

---

## 6. Fluxos do Sistema

### 6.1 Fluxo de Cadastro de Empresa

```
[Usuário acessa "Nova Empresa"]
  ↓
Passo 1: CNPJ + Nome + Conta de Conciliação
  → Validação CNPJ (CnpjValidationRule + dígitos verificadores)
  → Validação unicidade (user_id + cnpj)
  ↓
Passo 2: Contas Contábeis e Códigos de Histórico
  → conta_juros, conta_descontos, conta_pagamentos
  → codigo_historico_juros, _descontos, _pagamentos
  ↓
Passo 3: Parâmetros da Auditoria
  → percentual_min_pago (default 85%)
  → meses_tolerancia_desconto (default 3 na UI, 1 no banco)
  → meses_tolerancia_sem_pagamentos (default 3 na UI, 6 no banco)
  ↓
Passo 4: Configurações de Importação
  → parcelar, valor_minimo_parcela, valor_maximo_parcela / numero_maximo_parcelas
  → inicio_periodo_pagamento, fim_periodo_pagamento
  → parcela_preferencial_get / _set
  ↓
handleRecordCreation():
  → Empresa::create($data + ['user_id' => Auth::id()])
  → $empresa->conciliacao()->create()  ← cria conciliação vazia automaticamente
```

### 6.2 Fluxo Principal de Conciliação (Wizard)

```
Etapa 0 — Upload do Arquivo
  → FileUpload → afterStateUpdated → $this->save()
  → $record->fill($data)->save()
  → ConciliacaoService::store(empresa, pathFile)
      → Auditar::execute() → array de contas processadas
      → Store::execute() → persiste no banco
  → $this->fileProcessed = true → refresh dos componentes

Etapa 1 — Visão Geral
  → ConciliacaoOverview::getStats()
  → Exibe: total contas, pagamentos, notas fiscais, ajustes, erros, desbalanceadas

Etapa 2 — Selecionar Contas (ConciliacaoAjustes)
  → Tabela de contas_conciliadas (filtro padrão: somente desbalanceadas)
  → Usuário seleciona contas
  → [Botão "Confirmar contas e continuar"]
      → JS coleta IDs dos checkboxes (escopo $el.closest('[wire:id]'))
      → $wire.call('confirmarSelecionadasFromFooterWithIds', ids)
          → session()->put("contas_para_conciliar_{$id}", $ids)
          → ErroPagamento::whereIn('conta_conciliada_id', $ids)->exists()
          → $this->dispatch('contas-confirmadas')
          → retorna { hasErrors: bool }
      → Se hasErrors: requestNextStep() (ir para step 3)
      → Se !hasErrors: goToNextStep(2) (pular para step 4)

Etapa 3 — Corrigir Erros (CorrigirErros) [condicional]
  → Tabela de erros_pagamentos das contas selecionadas
  → EditAction inline para corrigir sugestao_numero_nota
  → [Próximo] avança para step 4

Etapa 4 — Notas Fiscais (NotasFiscais)
  → Tabela de notas filtradas por contas da sessão
  → Grouping por ContaConciliada ou Data
  → Filtros: conta, balanceado, período, tipo
  → Colunas calculadas: juros (valor_pago - valor p/ com_juros_paga), desconto
  → ViewColumn 'pagamentos' (lista pagamentos da nota)
  → Seleção + [Confirmar Notas]
      → $wire.call('marcarSelecionadasFromFooterWithIds', ids)
      → session()->put("notas_para_exportar_{$id}", $ids)

Etapa 5 — Pagamentos (Pagamentos)
  → Tabela de pagamentos filtrados por contas da sessão
  → Grouping por ContaConciliada, Data ou Número da Nota
  → Filtros: conta, balanceado, período, tipo
  → Seleção + [Confirmar Pagamentos]
      → $wire.call('marcarSelecionadasFromFooterWithIds', ids)
      → session()->put("pagamentos_para_exportar_{$id}", $ids)

Etapa 6 — Exportação [NÃO IMPLEMENTADO]
```

### 6.3 Fluxo de Atualização Reativa

```
[contas-confirmadas] (evento Livewire)
  ├── CorrigirErros::atualizarContas() → re-lê sessão, atualiza $hasErrors
  ├── NotasFiscais::atualizarContas()  → limpa "notas_para_exportar_{id}"
  └── Pagamentos::atualizarContas()    → limpa "pagamentos_para_exportar_{id}"
  → Livewire re-renderiza os componentes, table() re-consulta com nova sessão
```

---

## 7. Entrada e Saída de Dados

### 7.1 Formatos de Entrada (Arquivo de Balanço)

O sistema aceita três formatos de arquivo:

#### 7.1.1 TXT / genérico (separação por tabulação)

```
Conta: 12345 - Nome da Conta
\t(ignorada)
DD/MM/YYYY\tTIPO HISTÓRICO\tCOD\tDOC\tDEBITO\tCREDITO\tSALDO_BANCO
...
Saldo Anterior\t...\t\t...\t\t\tSALDO
```

**Estrutura de uma linha de transação:**

- Colunas separadas por tabulação (`\t`) ou derivadas de uma planilha convertida em linha com `implode("\\t", $fields)`.
- Mapeamento esperado por índice (0-based) quando convertido para array:

  - `0` — Data: formato `DD/MM/YYYY` (ex.: `05/03/2026`).
  - `1` — Tipo / Histórico: texto livre que contém palavras-chave (ex.: `NOTA FISCAL`, `PAGAMENTO DUPLICATA`).
  - `2` — Código: código auxiliar/opcional (ex.: centro de custo).
  - `3` — Documento: identificador do documento/cheque/recibo (ex.: `DOC123456`).
  - `4` — Débito: valor debitado (decimal), usar `.` ou `,` como separador conforme pré-processamento.
  - `5` — Crédito: valor creditado (decimal).
  - `6` — Saldo do banco (opcional): decimal ou vazio.

**Regex/validações aplicadas pelo parser:**

- Linha válida de transação: `/^\\d{1,2}\\/\\d{1,2}\\/\\d{4}/` (começa com data).
- `Saldo Anterior` detectado por `str_contains($line, 'Saldo Anterior')`.

**Exemplo de bloco de conta (TXT/CSV/XLSX convertido):**

```
Conta: 12345 - Caixa Geral
05/03/2026	NOTA FISCAL 2026/001		NF001	0.00	1000.00	1000.00
10/03/2026	PAGAMENTO NOTA FISCAL		DOC0001	1000.00	0.00	1000.00
Saldo Anterior						0.00
```

O parser transforma cada linha em array via `explode("\\t", $line)` e converte valores usando `parseValue()` centralizado, tratando `,` como separador decimal quando necessário.

#### 7.1.2 CSV

Mesmo formato do TXT, parseado via `Maatwebsite\Excel` com `toArray()`.

#### 7.1.3 XLSX

Lido via `Maatwebsite\Excel` com `memory_limit` temporariamente elevado para `512M`. Cada linha da planilha é convertida com `implode("\t", $fields)` para ser processada pelo mesmo parser do TXT.

**Tipos de histórico reconhecidos pelo parser:**

| Palavra-chave | Ação |
|---------------|------|
| `NOTA FISCAL` (sem PAGAMENTO/DUPLICATA) | Registra nota fiscal |
| `PAGAMENTO DUPLICATA` | Registra pagamento de duplicata |
| `PAGAMENTO NOTA FISCAL` | Registra pagamento de NF |
| `LANCTO REF. DESCONTO OBTIDO REF. NFE` | Registra pagamento com desconto |
| `DEVOLUÇÃO DE COMPRAS CONF. NF` | Registra devolução |
| `Saldo Anterior` | Extrai saldo anterior da conta |

### 7.2 Armazenamento do Arquivo

- Disco: `uploads`
- Diretório: `user/{userId}/`
- Visibilidade: `public`
- Forma de referência no banco: caminho relativo em `conciliacoes.file`
- Preservam-se os nomes originais dos arquivos.

### 7.3 Output — Arquivo de Importação

**Formato:** CSV com delimitador `,`, valores entre `"..."`.

**Headers:** `data, codigo_a, numero_conta, valor, codigo_c, numero_nota`

**Conteúdo:** Apenas pagamentos do tipo `com juros`, com parcelamento automático conforme `Config`.

### 7.4 Output — Relatório HTML/PDF

- Template: `resources/views/relatorios/balanco.blade.php`
- Dados: resultado completo de `Auditar::execute()` (todas as contas, notas, pagamentos, problemas, totais).
- PDF gerado via `wkhtmltopdf` em `/usr/bin/wkhtmltopdf`.

---

## 8. Relatórios e Processamentos

### 8.1 Dashboard de Visão Geral (`ConciliacaoOverview`)

Widget do tipo `StatsOverviewWidget` com 6 métricas em tempo real:

| Stat | Fonte | Cor |
|------|-------|-----|
| Total de Contas | `conciliacao->contas->count()` | info / danger |
| Total de Pagamentos | `conciliacao->pagamentos->count()` | info |
| Total de Notas Fiscais | `conciliacao->notas->count()` | info |
| Total de Ajustes | `conciliacao->ajustes->count()` | info |
| Possíveis Erros | `conciliacao->possiveisErrosPagamento->count()` | danger / success |
| Contas Desbalanceadas | `conciliacao->contas()->where('balanceado', false)->count()` | danger / success |

> **Alerta de performance:** O dashboard usa `->contas` (carregamento eager de toda a coleção em memória) para `count()`. Em conciliações com muitas contas, isso pode ser ineficiente — deveria usar `->contas()->count()` com query SQL.

### 8.2 Tabela de Notas Fiscais (`NotasFiscais`)

Filament Table com:
- **Agrupamento** por ContaConciliada (padrão, colapsado) ou Data.
- **Colunas calculadas**: `juros` (valor_pago - valor quando `com_juros_paga`), `desconto` (valor - valor_pago quando `desconto_paga`).
- **Sumarizadores** por tipo de nota (contadores no rodapé de cada grupo).
- **Coluna ViewColumn** `pagamentos`: renderiza lista de pagamentos associados à nota (view customizada).
- **Filtros**: por conta, balanceado (padrão: desbalanceadas), período de data, tipo de nota.

### 8.3 Tabela de Pagamentos (`Pagamentos`)

Filament Table com:
- **Agrupamento** por ContaConciliada (padrão), Data ou Número da Nota.
- **Sumarizadores** por tipo de pagamento.
- **Filtros**: por conta, balanceado (padrão: desbalanceadas), período de data, tipo de pagamento.

### 8.4 Tabela de Correção de Erros (`CorrigirErros`)

- Lista `erros_pagamentos` das contas selecionadas.
- **EditAction** modal permite corrigir `sugestao_numero_nota` manualmente.
- Exibe número original vs. sugestão algorítmica com badge `warning`.

### 8.5 Auditoria de Dados

Todos os modelos principais implementam `OwenIt\Auditing\Contracts\Auditable`, registrando automaticamente na tabela `audits`:
- Criações, atualizações e deleções.
- Usuário responsável, IP, timestamps, valores antigos e novos.

---

## 9. Pontos Críticos

### 9.1 🔴 Etapa de Exportação Não Implementada (Step 7)

**Problema:** O Step 6 ("Exportação") do wizard existe na estrutura mas está completamente vazio no código (`// Não implementado ainda`). As sessões `notas_para_exportar_{id}` e `pagamentos_para_exportar_{id}` são populadas nas etapas anteriores mas nunca são consumidas.

**Impacto:** O fluxo principal do sistema — gerar o arquivo de importação contábil — está incompleto no wizard. O `GenerateFileImport` e `ConciliacaoService::generateImportFile()` existem mas não são chamados por nenhuma interface.

**Recomendação:** Implementar o Step 7 com:
- Botão de geração do arquivo CSV (chamando `ConciliacaoService::generateImportFile()`).
- Opção de download do arquivo.
- Integração com `ArquivoExportacao` para registrar a exportação.

---

### 9.2 🔴 Destruição Total dos Dados no Reupload

**Problema:** Em `Store::execute()`, a sequência `conciliacao->contas()->delete()` apaga em cascata **todos** os dados (contas, notas, pagamentos, ajustes, erros) antes de reprocessar.

**Impacto:**
- Qualquer correção manual feita em `erros_pagamentos.sugestao_numero_nota` é **perdida** se o arquivo for reupado.
- Não há histórico de versões das conciliações.
- Sem possibilidade de comparar duas versões do mesmo arquivo.

**Recomendação:** Implementar soft delete ou versionamento de conciliações. Considerar separar as correções manuais do ciclo de reprocessamento.

---

### 9.3 🔴 Step 7 Inexistente no Índice do Wizard

**Problema:** O código no `nextAction` referencia índices `4` e `5` para disparar `marcar-para-exportacao`:

```php
'x-on:click' => "if (getStepIndex(step) === 4 || getStepIndex(step) === 5) { 
    window.dispatchEvent(new CustomEvent('marcar-para-exportacao')); 
} else { requestNextStep(); }",
```

Os steps são 0-indexados. O Step "Notas Fiscais" é o índice **4** e "Pagamentos" é o **5** — correto conforme estrutura atual. Se um step for adicionado ou removido, esses índices hardcoded quebram silenciosamente.

**Recomendação:** Usar identificadores de step nomeados em vez de índices numéricos.

---

### 9.4 🟠 Performance — Eager Loading de Coleções Completas

**Problema:** Em `ConciliacaoOverview`:
```php
$conciliacao->contas->count()         // load completo da coleção
$conciliacao->pagamentos->count()     // idem
$conciliacao->notas->count()          // idem
$conciliacao->ajustes->count()        // idem
```

Com milhares de registros (ex: 4.293 pagamentos, 7.539 notas conforme mencionado), isso carrega **todos os registros em memória PHP** só para contar.

**Recomendação:** Substituir por queries SQL de contagem:
```php
$conciliacao->pagamentos()->count()   // SELECT COUNT(*) ... — eficiente
```

---

### 9.5 🟠 Ausência de Policy/Gate para Isolamento de Dados

**Problema:** Não há `Policy` Eloquent ou Gate registrado para garantir que um usuário só acesse suas próprias empresas/conciliações. O isolamento depende apenas de filtros manuais nas queries.

**Impacto:** Um usuário autenticado que adivinhe o `id` de uma empresa de outro usuário pode acessar a página de conciliação diretamente pela URL.

**Recomendação:** Implementar `EmpresaPolicy` com `authorize()` nas pages do Filament.

---

### 9.6 🟠 Modelo `ArquivoExportacao` Incompleto

**Problema:** A tabela `arquivos_exportacoes` e o model `ArquivoExportacao` existem mas contêm apenas `conciliacao_id` e timestamps. Não há campos para:
- Caminho/URL do arquivo gerado.
- Status da exportação.
- Tipo de exportação.
- Usuário que gerou.

**Recomendação:** Adicionar migração para colunas `path`, `status`, `gerado_por` antes de implementar o Step 7.

---

### 9.7 🟠 Inconsistência nos Defaults de Config (UI vs. Banco)

**Problema:** Há divergência entre os defaults definidos no Filament (`Components/Config.php`) e os defaults da migration:

| Campo | Default na Migration | Default na UI |
|-------|---------------------|--------------|
| `meses_tolerancia_desconto` | 1 | 3 |
| `meses_tolerancia_sem_pagamentos` | 6 | 3 |

**Impacto:** Empresas criadas programaticamente (ou que pulam o passo 3) obtêm comportamento diferente das criadas via UI.

**Recomendação:** Alinhar os defaults da migration com os da UI.

---

### 9.8 🟡 Ausência de Soft Delete nos Models (SoftDeletes Trait)

**Problema:** As migrações definem `deleted_at` nullable em quase todas as tabelas, mas nenhum Model usa `SoftDeletes` trait do Eloquent. O campo `deleted_at` nunca é preenchido pelo framework.

**Impacto:** O campo `deleted_at` existe mas nunca é usado. Deleções são hard deletes.

**Recomendação:** Adicionar `use SoftDeletes;` nos models ou remover o campo `deleted_at` das migrações para evitar confusão.

---

### 9.9 🟡 Chaves de Sessão como Comunicação Entre Steps

**Problema:** A comunicação entre o Step 2 (seleção de contas) e os Steps 4, 5 é feita via **sessão PHP** (`session()->put("contas_para_conciliar_{$id}")`), não via estado Livewire ou banco.

**Riscos:**
- Se a sessão expirar no meio do wizard, os dados são perdidos sem aviso.
- Múltiplas abas abertas para a mesma conciliação em empresas diferentes podem conflitar (a chave já inclui `$conciliacaoId`, mitigando parcialmente).
- Não há persistência — se o usuário fecha e reabre o browser, as seleções são perdidas.

**Recomendação:** Avaliar persistir as seleções em banco (tabela `selecoes_exportacao`) ou usar Livewire state com `#[Url]` para URLs persistentes.

---

### 9.10 🟡 Processamento Síncrono de Arquivo Grande

**Problema:** O upload e processamento do arquivo (`ConciliacaoService::store()`) ocorrem **sincronamente** no ciclo de request HTTP (dentro do `afterStateUpdated` do `FileUpload`). O `Auditar::execute()` pode ser lento para arquivos grandes.

**Impacto:** Timeout de request para arquivos muito grandes; má experiência do usuário (tela travada).

**Recomendação:** Mover o processamento para um Job na fila (`ProcessarConciliacaoJob`), mostrando um indicador de andamento (polling Livewire ou broadcasting).

---

### 9.11 🟡 Timeout e Dependência de Serviço Externo (ReceitaWS)

**Problema:** A consulta de CNPJ depende de API externa com timeout de 30s. Sem tratamento adequado de fallback, uma indisponibilidade do ReceitaWS pode travar o cadastro de empresa.

**Recomendação:** Tratar exceções de rede com fallback gracioso (permitir salvar sem dados complementares), e adicionar cache com Redis para respostas do ReceitaWS.

---

### 9.12 🟡 Parser de Arquivo Acoplado a Strings em Português

**Problema:** O `Auditar` detecta tipos de lançamento por `str_contains($type, 'PAGAMENTO DUPLICATA')`, `'NOTA FISCAL'`, `'Saldo Anterior'`, etc. Essas strings são específicas do formato exportado pelo software contábil atual do cliente.

**Impacto:** Se o cliente trocar de sistema contábil ou o sistema mudar o formato de exportação, o parser para de funcionar silenciosamente (sem erros, apenas sem classificar transações).

**Recomendação:** Tornar as palavras-chave configuráveis por empresa (adicionar ao `Config`), ou implementar um parser plugável por provider de arquivo.

---

### 9.13 🟡 `valor_maximo_parcela` Não Está no Model `Config`

**Problema:** A migration de `configs` cria a coluna `valor_maximo_parcela`, o formulário manipula o campo, mas o model `Config` **não inclui `valor_maximo_parcela` no array `$fillable`**.

**Impacto:** O campo nunca é salvo via mass assignment.

**Recomendação:** Adicionar `'valor_maximo_parcela'` ao `$fillable` de `Config.php`.

---

### 9.14 🟢 Pontos Positivos da Arquitetura Atual

- **Separação de responsabilidades** clara: o `Auditar` só audita, o `Store` só persiste, o `ConciliacaoService` orquestra.
- **Algoritmo de detecção de erros** sofisticado com `similar_text()` — boa heurística para erros de digitação comuns.
- **Auditoria completa** via OwenIt em todos os modelos — rastro de mudanças para compliance contábil.
- **Batch inserts** (1.000 registros/lote) no `Store` — bom desempenho de persistência.
- **Object Values** com Symfony Validator — validação robusta e reutilizável de tipos de domínio.
- **Wizard multi-step** com navegação condicional (pula etapa de erros se não houver erros) — boa UX.
- **Grouping e sumarizadores** nas tabelas do Filament — boa visibilidade dos dados.

---

*Fim do documento de análise — Fase 1 completa.*

---

## 10. Recomendações para Reaproveitamento e Documentação da Nova Versão

### 10.1 Reaproveitamento dos `ObjectValues`

- Objetivo: garantir consistência e validação em toda a plataforma (CNPJ, CPF, Decimal, Monetário, Percentual, Email).
- Ações recomendadas:
  1. Mover `app/ObjectValues` para um pacote interno (ex.: `packages/ra/objects`) para facilitar versionamento e testes isolados.
  2. Definir uma interface pública (Factory) para criação: `ObjectValue::make('Cnpj', $value)` ou helpers `Cnpj::from($value)`.
  3. Exportar métodos utilitários reutilizáveis: `masked()`, `sanitized()`, `toString()`, `format()`.
  4. Cobertura de testes unitários para cada VO (validações extremas, máscaras, conversões). Priorizar `Cnpj`, `Decimal` e `Monetario`.
  5. Documentar contrato de entrada/saída (tipos aceitos, exceções lançadas) em `docs/object-values.md`.

### 10.2 Documentação detalhada dos Fluxos dos Services (para a nova versão)

- Cada service deve ter um README curto com: propósito, entradas (tipos/formatos), saídas (estruturas/DB changes), erros possíveis e exemplos de uso.

- Proposta de conteúdo técnico para `app/Services/Conciliacao/README.md`:
  - Descrição resumida (1 parágrafo).
  - `Auditar::execute(diskPath, discountThreshold, monthsToConsiderClosed, ignoreAccounts)`
    - Entrada: `string $diskPath` (caminho em `uploads`), `float $discountThreshold`, `int $monthsToConsiderClosed`, `array $ignoreAccounts`.
    - Saída: `array` de contas com `totals`, `notas`, `problemas` e `balanceado`.
    - Exceções: falha de leitura do arquivo, memória insuficiente ao processar Excel.
    - Observações de performance: aumentar `memory_limit` temporariamente apenas na leitura de Excel.

  - `Store::execute(array $auditData, Empresa $empresa)`
    - Entrada: output de `Auditar::execute` + `Empresa` model.
    - Operações: `updateOrCreate` conciliacao, persistência em lotes (notas/ajustes) e `upsert` para pagamentos.
    - Efeito colateral: atualmente remove todas as `contas` existentes — documentar como comportamento intencional ou a ser alterado.

  - `GenerateFileImport::execute(string $distribuicao, Conciliacao $conciliacao)`
    - Entrada: `distribuicao` (`primeira`|`ultima`), `Conciliacao` com relações carregadas.
    - Saída: `string` CSV pronta para download.

  - `ConciliacaoService::store()`
    - Orquestra: chama `Auditar`, depois `Store`; deve ser transformado em Job assíncrono para arquivos grandes.

### 10.3 Exemplos concretos de uso (snippet)

```php
// Executar auditoria e persistir (sincrono)
$result = Auditar::execute($diskPath, 0.1, 3);
(new Store)->execute($result, $empresa);

// Gerar CSV de importação
$csv = GenerateFileImport::execute('ultima', $empresa->conciliacao);
file_put_contents(storage_path('app/exports/conciliacao_'.$empresa->id.'.csv'), $csv);
```

### 10.4 Documentação adicional a criar

- `docs/services.md` — fluxos detalhados, diagramas de sequência (mermaid) e contratos de API internos.
- `docs/object-values.md` — contratos e exemplos de uso de cada VO.
- `docs/migration-guides.md` — passos se optarem por mover `ObjectValues` para pacote.

---

*Fim do documento de análise — Fase 1 completa.*
