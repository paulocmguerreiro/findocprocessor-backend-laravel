# Plano: Reestruturação docs/system_spec com Apresentação Progressiva de Contexto

## Contexto

O crescimento dos ficheiros `docs/system_spec/` é um problema estrutural, não de volume. Com 2 features implementadas `01-features.md` tem 188 linhas — com 10 features terá ~950. O mesmo padrão afeta `02-shared` (305 lin hoje), `03-models`, `05-routes`. Uma skill tem de ler ficheiros inteiros para encontrar uma secção.

**Apresentação Progressiva de Contexto:** contexto carregado em camadas — do geral para o específico, lendo apenas o que é necessário para a tarefa actual.

---

## Estrutura Proposta (antes → depois)

```
docs/system_spec/             ANTES         DEPOIS
├── 00-index.md               —             [NOVO] índice global, ~70 linhas, sempre rápido
│
├── 01-features.md            188 lin       APAGADO → substituído por directório
├── 01-features/              —             [NOVO directório]
│   ├── categoria-documento.md              [NOVO] extraído de 01-features.md + partes de 02-shared.md
│   └── entidade.md                         [NOVO] extraído de 01-features.md + partes de 02-shared.md
│
├── 02-shared.md              305 lin       APAGADO → substituído por directório
├── 02-shared/                —             [NOVO directório]
│   ├── enums.md                            [NOVO] TipoMovimento, DirecaoOrdenacao, DocumentStatus
│   ├── http.md                             [NOVO] ApiResponse + Exception Handler
│   └── estados.md                          [NOVO] ciclo de estados + contratos
│
├── 03-models.md              121 lin       APAGADO → substituído por directório
├── 03-models/                —             [NOVO directório]
│   ├── categoria-documento.md              [NOVO] extraído de 03-models.md
│   └── entidade.md                         [NOVO] extraído de 03-models.md
│
├── 04-infra.md               71 lin        APAGADO → substituído por directório (ver nota)
├── 04-infra/                 —             [NOVO directório]
│   ├── transactions.md                     [NOVO] padrão obrigatório já documentado
│   ├── repositories.md                     placeholder — quando implementado
│   ├── cache.md                            placeholder — Redis
│   ├── queue-jobs.md                       placeholder — Jobs/Queue
│   └── external-apis.md                    placeholder — IA + APIs externas
│
├── 05-routes.md              87 lin        APAGADO → substituído por directório
├── 05-routes/                —             [NOVO directório]
│   ├── categorias-documento.md             [NOVO] extraído de 05-routes.md
│   └── entidades.md                        [NOVO] extraído de 05-routes.md
│
└── 06-config.md              37 lin        SEM ALTERAÇÃO (razoável, cresce lentamente)
```

---

## As 4 camadas de contexto

```
Camada 0 — CLAUDE.md (sempre carregado)
  └── Regras + SYSTEM_SPEC_MAP actualizado. NUNCA detalhe de feature.

Camada 1 — 00-index.md (~70 linhas, entrada obrigatória)
  └── "O que existe e onde está" — tabela de features + shared + infra

Camada 2 — ficheiro específico da feature/modelo/rota (1 ficheiro)
  └── 01-features/entidade.md OU 03-models/entidade.md OU 05-routes/entidades.md

Camada 3 — shared ou infra (1 ficheiro de concern)
  └── 02-shared/enums.md OU 04-infra/transactions.md
```

---

## Tarefas

### T1 — Criar `00-index.md`

Conteúdo-tipo:
```markdown
# System Spec — Índice

> Ler antes de qualquer actualização. Depois abrir apenas o ficheiro indicado.

## Features implementadas
| Feature | Ficheiro | Actions | Rotas |
|---|---|---|---|
| CategoriaDocumento | 01-features/categoria-documento.md | 5 CRUD | 5 REST |
| Entidade | 01-features/entidade.md | 7 (5+converter+remover) | 5+1 especial |

## Features planeadas
[lista actual de 01-features.md — Documents, Upload, Batch, Files, Sse...]

## Shared
| Componente | Ficheiro |
|---|---|
| Enums (TipoMovimento, DirecaoOrdenacao) | 02-shared/enums.md |
| HTTP (ApiResponse, ExceptionHandler) | 02-shared/http.md |
| Estados + Contratos | 02-shared/estados.md |

## Modelos
[tabela: Model → 03-models/<slug>.md]

## Infra
[tabela: Redis→cache.md | Jobs→queue-jobs.md | Repos→repositories.md | IA→external-apis.md | Transacções→transactions.md]

## Rotas e Configuração
[tabela: Feature → 05-routes/<slug>.md | Config → 06-config.md]
```

### T2 — Criar `01-features/categoria-documento.md`

Extrair de `01-features.md` (linhas 7–82) + mover de `02-shared.md`:
- DTOs com código PHP completo (linhas 24–91 em 02-shared.md)
- CategoriaDocumentoResource (JSON shape)
- CriarCategoriaRequest / ActualizarCategoriaRequest (tabelas de regras)

### T3 — Criar `01-features/entidade.md`

Extrair de `01-features.md` (linhas 85–174) + mover de `02-shared.md`:
- DTOs com código PHP completo (linhas 133–183 em 02-shared.md)
- EntidadeResource (JSON shape)

### T4 — Criar `02-shared/enums.md`

Extrair de `02-shared.md` secção `## Enums`:
- `TipoMovimento`, `DirecaoOrdenacao` (com código PHP)
- Placeholder `DocumentStatus`

### T5 — Criar `02-shared/http.md`

Extrair de `02-shared.md`:
- `## HTTP` (ApiResponse — tabela de métodos)
- `## Exception Handler` (mapeamento de excepções → HTTP)
- `## Exceptions` (placeholder)

### T6 — Criar `02-shared/estados.md`

Extrair de `02-shared.md`:
- `## States` (ciclo de documento)
- `## Contracts` (placeholder)
- `## DTOs (app/Shared/DTOs/)` (placeholder shared global)

### T7 — Criar `03-models/categoria-documento.md` e `03-models/entidade.md`

Dividir `03-models.md` pelo limite `---` entre modelos. Manter o modelo Document esboçado em `03-models/documento.md` (feature pendente).

### T8 — Criar `04-infra/transactions.md` e placeholders

Mover a secção `## Transações de BD` (linhas 5–35 de `04-infra.md`) para `04-infra/transactions.md`.
Criar ficheiros placeholder com uma linha cada:
- `repositories.md`, `cache.md`, `queue-jobs.md`, `external-apis.md`

### T9 — Criar `05-routes/categorias-documento.md` e `05-routes/entidades.md`

Dividir `05-routes.md`:
- Rotas de CategoriaDocumento → `05-routes/categorias-documento.md`
- Rotas de Entidade → `05-routes/entidades.md`
- Features planeadas → manter em `00-index.md` como referência, ou criar `05-routes/planeadas.md`

### T10 — Apagar ficheiros monolíticos substituídos

```
docs/system_spec/01-features.md   → APAGAR
docs/system_spec/02-shared.md     → APAGAR
docs/system_spec/03-models.md     → APAGAR
docs/system_spec/04-infra.md      → APAGAR
docs/system_spec/05-routes.md     → APAGAR
```

### T11 — Actualizar SYSTEM_SPEC_MAP em `CLAUDE.md`

**Antes:**
```markdown
| Nova Action ou Feature                | `01-features.md`                  |
| Novo estado, contrato, DTO ou enum    | `02-shared.md`                    |
| Novo Model ou relação Eloquent        | `03-models.md`                    |
| Novo Repository, Provider, Job, Cache | `04-infra.md`                     |
| Nova rota API                         | `05-routes.md`                    |
| Nova configuração ou .env var         | `06-config.md`                    |
```

**Depois:**
```markdown
| Nova Action ou Feature (feature existente) | `01-features/<slug>.md`                         |
| Nova Feature (slice nova)                  | criar `01-features/<slug>.md` + `00-index.md`   |
| Novo enum partilhado                       | `02-shared/enums.md`                            |
| Novo componente HTTP ou handler de erro    | `02-shared/http.md`                             |
| Novo estado ou contrato                    | `02-shared/estados.md`                          |
| Novo Model ou relação Eloquent             | `03-models/<slug>.md`                           |
| Novo Repository                            | `04-infra/repositories.md`                      |
| Novo Job ou Queue config                   | `04-infra/queue-jobs.md`                        |
| Cache ou Redis                             | `04-infra/cache.md`                             |
| API externa (IA ou outro)                  | `04-infra/external-apis.md`                     |
| Nova rota API                              | `05-routes/<slug>.md`                           |
| Nova configuração ou .env var              | `06-config.md`                                  |
```

### T12 — Actualizar skill `actualiza-spec.md`

A skill precisa de:
1. Ler `00-index.md` para descoberta (quando não sabe qual ficheiro)
2. Para features: abrir `01-features/<slug>.md` directamente (slug vem do workflow-state)
3. Para modelos/rotas: `03-models/<slug>.md`, `05-routes/<slug>.md`
4. Para shared/infra: SYSTEM_SPEC_MAP aponta directamente para o ficheiro certo

---

## Sobre `04-infra` — recomendação

**Opinião:** Sim, converter agora mesmo sendo pequeno (71 linhas).

Motivo: `04-infra` vai ser o ficheiro mais heterogéneo do sistema quando o projeto amadurecer. Transações, Repositories, Jobs, Redis e APIs de IA são subsistemas com padrões completamente distintos. Se esperarmos para dividir, o ponto de dor chega a meio de uma implementação complexa (ex: integração de IA) — pior momento para refactorizar docs.

O padrão de transações já está documentado e é usado em todas as Actions de escrita — faz sentido ter o seu próprio ficheiro agora. Os placeholders das outras áreas custam 5 linhas cada e estabelecem o padrão antes que o conteúdo apareça.

**Regra de sustentabilidade para infra:** cada novo subsistema (Redis, IA, novo provider) abre/actualiza o seu ficheiro específico em `04-infra/`. Nunca se juntam conceitos heterogéneos no mesmo ficheiro.

---

## Regras de sustentabilidade (a adicionar à skill `actualiza-spec`)

1. Nova feature slice → criar `01-features/<slug>.md` (nunca acrescentar ao ficheiro de outra feature)
2. `02-shared/` → apenas componentes em `app/Shared/` (nunca feature-specific)
3. `04-infra/` → um ficheiro por subsistema de infra (Redis ≠ Jobs ≠ Repositories)
4. `00-index.md` → actualizar sempre que um ficheiro novo é criado (fica a "porta de entrada")
5. `06-config.md` → pode permanecer ficheiro único (cresce linearmente com .env vars)

---

## Verificação

1. `00-index.md` lista as 2 features + todas as planeadas em < 70 linhas
2. `01-features/categoria-documento.md` contém tudo sobre a feature (Actions, DTOs com código, Policy, FormRequests, Controller, Resource)
3. `02-shared/` não contém qualquer referência a `CategoriaDocumento` ou `Entidade` (apenas shared puro)
4. `04-infra/transactions.md` contém o padrão documentado; outros ficheiros existem como placeholder
5. CLAUDE.md `SYSTEM_SPEC_MAP` aponta para os novos caminhos
6. Skill `actualiza-spec` usa `00-index.md` como ponto de descoberta
7. Sem referências aos ficheiros monolíticos apagados em commands/skills
