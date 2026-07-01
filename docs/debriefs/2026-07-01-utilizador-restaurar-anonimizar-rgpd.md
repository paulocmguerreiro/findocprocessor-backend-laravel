# Debrief: Utilizador — Restaurar soft-deleted + RGPD Anonimização

**Issue:** #73
**Branch:** feat/utilizador-restaurar-anonimizar-rgpd
**Data:** 2026-07-01
**Commits:** 11 commits (implementação) + brief/spec/plan + workflow

## O que foi implementado

As duas operações de ciclo de vida que faltavam à feature `Utilizador` sobre registos soft-deleted: **restauro** (inverso do Eliminar) e **anonimização RGPD** (Art. 17.º — substitui dados pessoais em vez de hard delete, preservando as FKs `restrictOnDelete`).

- **`RestaurarUtilizadorAction`** (nova) — `handle(User|int): User`; resolve `int` via `withTrashed()->findOrFail()`, `Gate::authorize('restore')` fora da transação, duas invariantes (`! trashed()` e email `anonimizado+`), `restore()` + invalidação de cache dentro. Devolve `->load('roles')`.
- **`AnonimizarUtilizadorAction`** (nova) — `handle(User): void`; `Gate::authorize('anonimizar')` + invariantes (auto-anonimização, já-anonimizado) fora da transação; dentro: revoga tokens → `forceFill([...])->saveQuietly()` → evento manual `rgpd.anonimizacao` → soft delete → invalida cache.
- **`RestaurarUtilizadorRequest` / `AnonimizarUtilizadorRequest`** (novos) — dupla camada de autorização (RMB no FormRequest).
- **`UtilizadorPolicy::restore()`** (reutiliza `utilizadores.eliminar`) + **`anonimizar()`** (nova permissão `utilizadores.anonimizar`).
- **`User` passa a usar `RegistaActividade`** — audita o CRUD normal (`name`/`email`), exclui `password`/`remember_token`. Habilita o audit trail administrativo do utilizador.
- **Rotas** `PATCH /utilizadores/{utilizador}/restaurar` (com `->withTrashed()`) e `POST /utilizadores/{utilizador}/anonimizar`.
- **Migration** `seed_utilizadores_anonimizar_permission` — permissão `utilizadores.anonimizar` → role `admin`.
- **Testes:** 4 ficheiros novos (28 testes: Unit + Feature de cada Action, incluindo audit sem PII e revogação de token end-to-end); 3 ficheiros corrigidos pela regressão de auditoria do `User`. Suite global: **724 testes**, 100% coverage e type-coverage, Larastan 9, verde.

## Ficheiros alterados

| Ficheiro | Tipo | Notas |
| -------- | ---- | ----- |
| `app/Features/Utilizador/Restaurar/RestaurarUtilizadorAction.php` | criado | `User\|int`; `withTrashed()->findOrFail`; 2 invariantes; restore + cache |
| `app/Features/Utilizador/Restaurar/RestaurarUtilizadorRequest.php` | criado | `Gate::authorize('restore', $this->route('utilizador'))` (RMB) |
| `app/Features/Utilizador/Anonimizar/AnonimizarUtilizadorAction.php` | criado | tokens + `forceFill()->saveQuietly()` + `activity('rgpd.anonimizacao')` + soft delete |
| `app/Features/Utilizador/Anonimizar/AnonimizarUtilizadorRequest.php` | criado | `Gate::authorize('anonimizar', $this->route('utilizador'))` (RMB) |
| `app/Policies/UtilizadorPolicy.php` | alterado | `restore()` (reutiliza `utilizadores.eliminar`) + `anonimizar()` |
| `app/Models/User.php` | alterado | trait `RegistaActividade` + `atributosExcluidosDaActividade()` = `['password','remember_token']` |
| `app/Features/Utilizador/UtilizadorController.php` | alterado | `restaurar()` (200+Resource) + `anonimizar()` (204) |
| `routes/api.php` | alterado | rotas `restaurar` (`->withTrashed()`) e `anonimizar` |
| `database/migrations/..._seed_utilizadores_anonimizar_permission.php` | criado | `utilizadores.anonimizar` → `admin` |
| `tests/Unit/Features/Utilizador/RestaurarUtilizadorActionTest.php` | criado | User/int PK/não-inactivo/anonimizado/rollback/403/401 |
| `tests/Unit/Features/Utilizador/AnonimizarUtilizadorActionTest.php` | criado | anonimiza+tokens/audit sem PII/auto/já-anonimizado/rollback/403/401 |
| `tests/Feature/Features/Utilizador/RestaurarUtilizadorTest.php` | criado | 200/422×2/404/reaparece em GET/403/401 |
| `tests/Feature/Features/Utilizador/AnonimizarUtilizadorTest.php` | criado | 204/422×2/404/403/401/token-inválido (E2E) |
| `tests/Feature/Features/Entidade/CriarEntidadeTest.php` | alterado | limpa `activity_log` após auth (User passou a auditar) |
| `tests/Feature/Features/CategoriaDocumento/CriarCategoriaTest.php` | alterado | idem |
| `tests/Feature/Features/Role/CriarRoleTest.php` | alterado | idem |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| **`User` usa `RegistaActividade`** + evento manual `rgpd.anonimizacao` | Manter o `User` fora do audit e usar `Log::info('rgpd.anonimizacao')` (o que o Brief inicial previa) | O Spec (mais recente) definiu audit trail persistente e consultável para o utilizador; o `saveQuietly()` suprime o `updated` automático (que teria `old.name`/`old.email` — PII) e a Action regista o evento sem propriedades |
| **`saveQuietly()`** na substituição de dados | `save()` normal | `save()` dispararia o evento `updated` do trait, gravando `old.name`/`old.email` no `activity_log` (PII). `saveQuietly()` suprime-o; a prova de anonimização fica no evento manual sem campos |
| **`forceFill()`** em vez de `fill()`/`update()` | `update([...])` | `remember_token` e `email_verified_at` **não** estão em `$fillable` do `User`; `forceFill` ignora a guarda |
| Invariantes do Anonimizar **fora** da transação | Dentro da transação (como o Brief sugeria) | São pré-verificações em memória (auto-anonimização, prefixo de email); não dependem de estado que a transação proteja — alinhado com `EliminarUtilizadorAction` |
| Assinatura `User\|int` no Restaurar (não `User\|string`) | `User\|string` (padrão dos modelos UUID) | A PK do `User` é `int` (excepção documentada — modelo de autenticação); o ramo programático resolve por `int` |
| Teste de token-inválido faz `forgetGuards()` entre pedidos | Um único pedido / asserção só em BD | O guard `sanctum` memoriza o utilizador resolvido no 1.º pedido; sem `forgetGuards()` o 2.º pedido reusaria o admin. `forgetGuards()` força reautenticação pelo token do alvo (revogado → 401) — teste E2E real |

## Desvios ao Plano

- **Regressão de auditoria não prevista.** Adicionar `RegistaActividade` ao `User` fez cada criação de utilizador (incluindo o admin autenticado no setup) registar um evento `created`. Isto quebrou 6 asserções `Activity::count()` em `Criar{Entidade,Categoria,Role}Test` (contavam 1/0, passaram a 2/1). Corrigido limpando `activity_log` **após** a autenticação — isola a contagem à actividade do próprio pedido, mantendo a intenção original dos testes.
- **Asserção "sem PII" reformulada.** O plano (T8) previa verificar `Activity::where('event','rgpd.anonimizacao')` "sem campos PII". Na prática o evento `created` do factory contém legitimamente o email (audit normal de CRUD, correcto). A asserção final verifica antes que **nenhum** evento `updated` foi gerado para o alvo (o `saveQuietly()` suprimiu-o) e que o evento `rgpd.anonimizacao` tem propriedades vazias.
- **Brief vs Spec divergiam** no tratamento do audit (o Brief dizia `Log::info` e `User` sem trait; o Spec dizia trait + `activity()` manual). Seguiu-se o Spec por ser o artefacto mais recente e alinhado com a infra de audit trail existente.

## Aprendizagens

- **Adicionar um trait a um modelo partilhado é uma alteração transversal, não local.** O `User` é criado no setup de praticamente todos os testes de feature. Pôr `RegistaActividade` nele fez emergir actividade em suites que nada tinham a ver com a issue — só os 3 testes que contavam `Activity` exacto falharam, mas o efeito colateral é global. Lição de Vertical Slice: uma slice pode ser vertical no código e horizontal no *runtime* quando toca um modelo de fronteira (autenticação). Vale medir o raio de impacto (correr a suite toda) antes de assumir que a mudança é contida.
- **`saveQuietly()` é a ferramenta certa para "muta sem auditar automaticamente".** O padrão anonimização precisava de dois efeitos opostos sobre o mesmo `save`: persistir a mudança **mas** não deixar o audit automático gravar os valores antigos (que são exactamente a PII a eliminar). `saveQuietly()` + `activity()` manual separa cleanly a persistência da narrativa de auditoria — o audit passa a dizer "foi anonimizado por X" em vez de "name mudou de Ana para Utilizador #7".
- **A auditoria RGPD é sobre *prova sem retenção*.** Anonimizar não é apagar o rasto — é manter prova de que a operação ocorreu (quem, quando, sobre quem) sem reter os dados que a operação eliminou. Um evento `deleted`/`updated` cru violaria isso; o evento custom sem propriedades é o compromisso correcto.
- **O guard Sanctum memoriza dentro do mesmo teste.** Dois pedidos autenticados num só teste partilham o container: o guard `sanctum` cacheia o utilizador do 1.º pedido. Testar revogação de token exige `forgetGuards()` entre pedidos, senão o 2.º "vê" ainda o utilizador anterior e o teste passa por engano (falso verde). Um teste de segurança que passa sem exercitar o mecanismo é pior que nenhum.
- **Quando dois artefactos do processo divergem, o mais recente e mais integrado ganha.** Brief e Spec discordavam sobre o audit; escolher o Spec (posterior, alinhado com `RegistaActividade` já existente) evitou reintroduzir `Log::info` numa base que já tinha audit trail persistente. Verificar a infra real (`grep RegistaActividade`) confirmou a escolha antes de escrever código.

## SYSTEM_SPEC actualizado

- `docs/system_spec/00-index.md` — Utilizador passa a 8 Actions / 5 REST + 3 especiais.
- `docs/system_spec/01-features/utilizador.md` — `RestaurarUtilizadorAction`, `AnonimizarUtilizadorAction`, secções Restauro/RGPD, policy `restore`/`anonimizar`.
- `docs/system_spec/05-routes/role.md` — rotas `restaurar` + `anonimizar`, tabela de respostas.
- `docs/system_spec/04-infra/autorizacao.md` — permissão `utilizadores.anonimizar` + matriz.
- `docs/system_spec/04-infra/audit-trail.md` — `User` como modelo auditado, evento custom `rgpd.anonimizacao`, nota da regressão nos testes Criar.
- `openapi.yaml` — endpoints `restaurar` (200) e `anonimizar` (204).

## Verificação final

- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (724 testes, 100% coverage + type-coverage, Larastan 9, arch)
- [x] Nenhum dado sensível em logs (`password`/`remember_token` excluídos do audit; evento `rgpd.anonimizacao` sem propriedades; sem `Log::` com PII)
- [x] Nenhum segredo em código (`password` anonimizado com `Hash::make(Str::random(64))`)
- [x] Autorização dupla camada (`Gate::authorize` no Request **e** na Action)
- [x] Tokens Sanctum revogados na anonimização (verificado E2E: token do alvo → 401)
