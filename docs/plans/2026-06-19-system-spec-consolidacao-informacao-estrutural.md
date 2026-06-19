# Análise: Informação estrutural fora do `docs/system_spec`

## Contexto

O `docs/system_spec` deve ser a fonte de verdade para tudo o que descreve **o que o sistema é e como funciona** — decisões arquitecturais, contratos, padrões canónicos, convenções estruturais. Actualmente, grande parte dessa informação vive dispersa em três outros locais:

- **`CLAUDE.md`** — instruções de agente, não documentação do sistema
- **`.claude/skills/` e `.claude/commands/`** — orquestração de workflow, não definição arquitectural
- **`docs/conventions/`** — pasta paralela ao system_spec com sobreposição de responsabilidade

O resultado: para entender "como é que este sistema funciona?", é preciso ler 4+ ficheiros em locais diferentes.

---

## Listagem de itens a migrar

### 1. Padrão de Autorização Dupla Camada

**Localização actual:** `CLAUDE.md` (secção ARQUITECTURA), `.claude/skills/escreve-spec.md` (verification checklist), `.claude/commands/cria-issue-logica.md`

**O que é:** Regra arquitectural obrigatória — `Gate::authorize()` deve ser chamada tanto no FormRequest (camada HTTP) como na Action (camada de lógica), com justificação explícita de porquê não é redundância.

**Onde deveria estar:** `docs/system_spec/02-shared/` → novo ficheiro `padroes-acoes.md` ou expandido em `http.md`

---

### 2. Padrão DB::transaction() nas Actions de Escrita

**Localização actual:** `CLAUDE.md` (secção ARQUITECTURA, com exemplos de código), `docs/system_spec/04-infra/transactions.md` (padrão canónico presente mas incompleto — falta: `@throws \Throwable` obrigatório no PHPDoc, nota sobre `ShouldDispatchAfterCommit`)

**O que é:** Contrato obrigatório para todas as Actions de escrita — estrutura canónica com autorização fora e persistência dentro, com código de exemplo.

**Onde deveria estar:** `docs/system_spec/04-infra/transactions.md` — completar com os elementos em falta do `CLAUDE.md`

---

### 3. Critério de Decisão Repository (obrigatório vs dispensável)

**Localização actual:** `CLAUDE.md` (secção ARQUITECTURA — critérios detalhados), `docs/system_spec/04-infra/repositories.md` (apenas "Pendente — ver critérios em CLAUDE.md")

**O que é:** Regra arquitectural que define quando o Repository é obrigatório (joins, aggregates, raw SQL, queries partilhadas ≥ 2 Actions) vs dispensável (CRUD simples, ≤ 1 query por handle(), sem lógica partilhada).

**Onde deveria estar:** `docs/system_spec/04-infra/repositories.md` — os critérios devem estar no system_spec, não delegados ao CLAUDE.md

---

### 4. Ciclo de Estados do Documento (DocumentStatus)

**Localização actual:** `CLAUDE.md` (diagrama ASCII do ciclo), `docs/system_spec/02-shared/estados.md` (vazio — "vazio até à primeira issue"), `docs/system_spec/02-shared/enums.md` (DocumentStatus marcado como "pendente")

**O que é:** Máquina de estados central da aplicação — 7 estados, 5 transições legítimas + 2 erros. É o contrato de domínio mais importante do sistema.

**Onde deveria estar:** `docs/system_spec/02-shared/estados.md` + `docs/system_spec/02-shared/enums.md` — actualmente ambos estão quase vazios apesar de o CLAUDE.md ter o diagrama completo

---

### 5. Padrão DTO Value Object

**Localização actual:** `CLAUDE.md` (secção CONVENÇÕES DE TIPAGEM — código de exemplo completo com construtor, fromRequest(), @throws), `.claude/commands/cria-issue-modelo.md` (critérios de aceitação por camada), `.claude/commands/cria-issue-persistencia.md`

**O que é:** Padrão estrutural obrigatório para todos os DTOs — `final readonly class`, construtor valida invariantes (`\InvalidArgumentException`), `fromRequest()` só mapeia, divisão de responsabilidades FormRequest/DTO/Action.

**Onde deveria estar:** `docs/system_spec/02-shared/` → novo ficheiro `padroes-dtos.md`

---

### 6. Convenções de Tipagem (Regra A e Regra B)

**Localização actual:** `CLAUDE.md` (secção CONVENÇÕES DE TIPAGEM — Regra A: array shape em validated(), Regra B: @throws obrigatório em métodos com throw)

**O que é:** Dois contratos de qualidade de código obrigatórios em toda a codebase — eliminação de `mixed` via `@var` array shape, e declaração `@throws` em todos os métodos que lançam excepções.

**Onde deveria estar:** `docs/system_spec/02-shared/` → novo ficheiro `padroes-tipagem.md` ou integrado em `padroes-acoes.md`

---

### 7. Convenções de Nomenclatura (Língua, Métodos, Variáveis, Enums, FKs)

**Localização actual:** `CLAUDE.md` (secção CONVENÇÕES DE NOMENCLATURA — completa com exemplos), `.claude/skills/escreve-spec.md` (sub-secção sobre convenções), `.claude/commands/cria-issue-logica.md`

**O que é:** Regras de naming que afectam todo o código de domínio — quando usar PT vs EN, padrão VERBO+Intenção para métodos, NOME+Intenção[+Escala] para variáveis, TitleCase PT para enums, `id_<entidade>` para FKs.

**Onde deveria estar:** `docs/system_spec/02-shared/` → novo ficheiro `convencoes-nomenclatura.md`

---

### 8. Convenção de Paginação (Cursor Obrigatório)

**Localização actual:** Memória persistente (`feedback_cursor_pagination.md`), `CLAUDE.md` (indirectamente via cria-issue-persistencia), `.claude/commands/cria-issue-persistencia.md` (critério de aceitação: "cursorPaginate() nunca OFFSET"), `docs/system_spec/05-routes/` (documentado por feature mas sem norma explícita)

**O que é:** Convenção universal de API — todas as listagens usam `cursorPaginate()` (keyset pagination), nunca `paginate()` com OFFSET. Campo de ordenação como enum PHP. Aplicada consistentemente em todas as features.

**Onde deveria estar:** `docs/system_spec/02-shared/http.md` → secção "Convenções de Listagem" ou `05-routes/` → secção partilhada

---

### 9. Padrão Dual de Testes (Unit + Feature)

**Localização actual:** `docs/conventions/tests-dual-pattern.md` (ficheiro autónomo em pasta paralela), `CLAUDE.md` (secção CONVENÇÕES DE TESTES — duplicado quase completo), `.claude/skills/pest-testing/SKILL.md`

**O que é:** Convenção estrutural — cada Action tem dois ficheiros de teste com responsabilidades distintas: Unit (programático, directo) e Feature (HTTP, externo). Inclui o que cada tipo cobre, o que nunca deve cruzar, e a estrutura de ficheiros esperada.

**Onde deveria estar:** `docs/system_spec/` → novo ficheiro `07-testing.md` (ou `02-shared/testes.md`). A pasta `docs/conventions/` é redundante se o system_spec for a fonte de verdade.

---

### 10. Verificação Arquitectural por Camada (Checklist)

**Localização actual:** `.claude/skills/escreve-spec.md` (verificação arquitectura laravel — lista de 5 itens), `.claude/commands/cria-issue-logica.md` (9 critérios de aceitação), `.claude/commands/cria-issue-modelo.md` (6 critérios), `.claude/commands/cria-issue-persistencia.md` (7 critérios), `.claude/skills/pausa-checkpoint.md` (Checkpoint B — lista de verificação)

**O que é:** Os critérios de aceitação por camada (lógica, modelo, persistência) são contratos arquitecturais da aplicação — não apenas critérios de workflow. São a definição do que é "correcto" por camada.

**Onde deveria estar:** `docs/system_spec/02-shared/` → integrado em `padroes-acoes.md` ou ficheiro `contratos-por-camada.md`

---

### 11. Padrão de Resposta da API (ApiResponse + RFC 7807)

**Localização actual:** `docs/system_spec/02-shared/http.md` (parcialmente documentado — métodos do ApiResponse e exception handler), mas sem documenter o contrato completo de uso — quando usar cada método, o que inclui cada resposta

**O que é:** Contrato de interface pública da API — estrutura de sucesso (`data`), estrutura de erro (RFC 7807 simplificado com `status` + `detail`), estrutura de paginação (`data` + `links` + `meta`).

**Onde deveria estar:** `docs/system_spec/02-shared/http.md` — expandir com exemplos de payload completo para cada tipo de resposta e mapeamento claro de quando usar cada método do ApiResponse

---

### 12. Atributos PHP Obrigatórios nos Models

**Localização actual:** `CLAUDE.md` (`@property-read` obrigatório em Eloquent Models), memória persistente (`feedback_eloquent_model_structure.md` — `#[Table]`, `#[Fillable]`, `#[Casts]`), por cada model em `docs/system_spec/03-models/`

**O que é:** Padrão estrutural obrigatório para todos os Eloquent Models — lista canónica de atributos PHP (`#[Table]`, `#[Fillable]`, `#[Casts]`), `@property-read` completo, `HasUuids` como PK, convenção de FK.

**Onde deveria estar:** `docs/system_spec/03-models/` → novo ficheiro `00-convencoes-models.md` com o padrão canónico (actualmente está implícito via sibling files e espalhado)

---

## Sumário

| # | Item | Local actual (principal) | Destino proposto |
|---|------|--------------------------|-----------------|
| 1 | Autorização dupla camada | `CLAUDE.md` | `02-shared/padroes-acoes.md` (novo) |
| 2 | DB::transaction() padrão completo | `CLAUDE.md` + `04-infra/transactions.md` (incompleto) | `04-infra/transactions.md` (completar) |
| 3 | Critério de decisão Repository | `CLAUDE.md` | `04-infra/repositories.md` (completar) |
| 4 | Ciclo de estados DocumentStatus | `CLAUDE.md` | `02-shared/estados.md` + `02-shared/enums.md` (completar) |
| 5 | Padrão DTO Value Object | `CLAUDE.md` | `02-shared/padroes-dtos.md` (novo) |
| 6 | Regra A e B tipagem | `CLAUDE.md` | `02-shared/padroes-tipagem.md` (novo) |
| 7 | Convenções nomenclatura | `CLAUDE.md` | `02-shared/convencoes-nomenclatura.md` (novo) |
| 8 | Paginação cursor obrigatório | Memória + skills | `02-shared/http.md` (expandir) |
| 9 | Padrão dual de testes | `docs/conventions/` + `CLAUDE.md` | `07-testing.md` (novo) |
| 10 | Verificação arquitectural por camada | `.claude/skills/` + `.claude/commands/` | `02-shared/contratos-por-camada.md` (novo) |
| 11 | Contrato ApiResponse completo | `02-shared/http.md` (parcial) | `02-shared/http.md` (expandir) |
| 12 | Padrões canónicos de Model | `CLAUDE.md` + memória | `03-models/00-convencoes-models.md` (novo) |

**Total: 12 itens — 5 ficheiros a completar, 7 ficheiros novos a criar em `docs/system_spec/`**
