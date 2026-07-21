# Debrief: Entidade — agrupar/fundir duplicados (repontar FKs + hard-delete)

**Issue:** #99
**Branch:** feat/entidade-agrupar-duplicados
**Data:** 2026-07-21
**Commits:** 6 commits (permissão+policy, inventário+excepção, action, refactor interface, endpoint, bump guzzle)

## O que foi implementado

Nova slice `app/Features/Entidade/Agrupar/` com um endpoint
`POST /api/entidades/{principal}/agrupar-com/{secundaria}` que funde duas entidades duplicadas
numa única operação atómica: reponta o UUID da `secundaria` para o da `principal` em todas as FKs
conhecidas (`documentos.id_fornecedor`, `documentos.id_cliente`), une os papéis `e_cliente`/
`e_fornecedor` por OR, e remove a `secundaria` permanentemente (`forceDelete()`, sem fallback para
soft-delete). Uma guarda de futuro (`InventarioReferenciasEntidade`) inspecciona o esquema real da
BD em runtime antes do hard-delete e falha com `422` se surgir uma FK para `entidades` fora da
allow-list — protege contra órfãos mesmo que uma FK nova (`nullOnDelete`/`cascadeOnDelete`) não
seja bloqueada pela BD. Nova permissão `entidades.agrupar` (só `admin`) com autorização dupla
camada (`AgruparEntidadeRequest` + `Gate::authorize` na Action).

## Ficheiros alterados

| Ficheiro / grupo | Tipo de alteração | Notas |
| ---------------- | ----------------- | ----- |
| `database/migrations/..._seed_entidades_agrupar_permission.php` | criado | seed da permissão, sincronizada a `admin` |
| `app/Policies/EntidadePolicy.php` | alterado | novo método `agrupar()` |
| `app/Features/Entidade/Agrupar/InventarioReferenciasEntidadeInterface.php` | criado | contrato de introspecção (Tarefa 2 refeita — ver Desvios) |
| `app/Features/Entidade/Agrupar/InventarioReferenciasEntidade.php` | criado | implementação real via `Schema::getForeignKeys()` |
| `app/Features/Entidade/Agrupar/AgrupamentoInvalidoException.php` | criado | `DomainException` → 422; 3 factories estáticas |
| `app/Features/Entidade/Agrupar/AgruparEntidadeAction.php` | criado | `final readonly`; injecta a interface + `CacheServico` |
| `app/Features/Entidade/Agrupar/AgruparEntidadeRequest.php` | criado | `rules(): []`, `authorize()` via `Gate::authorize` |
| `app/Features/Entidade/EntidadeController.php` | alterado | novo método `agruparCom()`, dispatch puro |
| `routes/api.php` | alterado | nova rota POST |
| `app/Providers/AppServiceProvider.php` | alterado | bind da interface → implementação concreta |
| `tests/ArchTest.php` | alterado | excepção da regra "actions are final" para a interface |
| `tests/Unit/Policies/EntidadePolicyTest.php` | alterado | casos `agrupar` admin/utilizador |
| `tests/Unit/Features/Entidade/InventarioReferenciasEntidadeTest.php` | criado | RF-05 contra esquema real |
| `tests/Unit/Features/Entidade/AgrupamentoInvalidoExceptionTest.php` | criado | mensagens das 3 factories |
| `tests/Unit/Features/Entidade/AgruparEntidadeActionTest.php` | criado | CA-01/02/03/07/08/09 |
| `tests/Feature/Features/Entidade/AgruparEntidadeTest.php` | criado | CA-01/03/04/07/09 via HTTP |
| `composer.lock` | alterado | bump `guzzlehttp/guzzle` 7.15.0 → 7.15.1 (3 advisories médios, WRN-039, fora do âmbito da issue) |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| `InventarioReferenciasEntidade` ganhou interface | Classe concreta sem interface (Plano original, Tarefa 2 — "introspecção pura, sem substituição prevista") | CA-08 exige simular uma FK nova não tratada sem manipular o esquema real (incompatível com testes em paralelo sobre BD partilhada); a única forma limpa de o fazer é trocar a implementação via bind no container. Desvio ao padrão "sem interface quando não há substituição prevista" — aqui há, e é técnica (testabilidade), não de produção. |
| Guarda de futuro por introspecção do esquema, complementar ao `restrictOnDelete` | Confiar só na FK da BD (`QueryException` → rollback) | Uma FK `nullOnDelete`/`cascadeOnDelete` futura não seria bloqueada pela BD e deixaria órfãos silenciosos; a guarda explícita apanha qualquer FK nova independentemente do `onDelete`, com erro `422` claro em vez de `QueryException` opaco. |
| Hard-delete sem fallback (não Padrão B) | Force-com-fallback para soft-delete | Decisão do Checkpoint B do Brief: como a repontagem acontece sempre antes do delete, nada referencia a secundária no momento da remoção — um `forceDelete()` a falhar é sinal de bug na allow-list, não um caso a tolerar silenciosamente. |
| União de papéis por OR (`e_cliente`/`e_fornecedor`), `e_empresa_aplicacao` intocado | Fusão campo-a-campo completa | Fora de âmbito (Brief); só os papéis têm de reflectir a repontagem de `documentos.id_fornecedor`/`id_cliente`, senão fica-se com FK apontando para uma entidade que "nega" o papel. |
| Mass update via `DB::table()->update()` para repontar FKs | `Documento::where(...)->update()` (Eloquent) | RNF-03 — não carregar documentos em memória; aceite conscientemente que não dispara eventos Eloquent nem audit trail por documento (auditoria fica ao nível da `Entidade`). |

## Desvios ao Plano

- **Tarefa 2 reaberta após a Tarefa 3.** O Plano previa `InventarioReferenciasEntidade` como
  classe concreta sem interface. Ao escrever o teste de CA-08 na Tarefa 3, ficou claro que simular
  uma FK não tratada exige substituir a implementação (o esquema real de testes é partilhado e
  corre em paralelo — não se pode acrescentar uma tabela/FK ad-hoc com segurança). Commit
  `898eb5e` introduziu `InventarioReferenciasEntidadeInterface` a posteriori, com bind no
  `AppServiceProvider` e excepção dedicada em `ArchTest.php`. Sem impacto no comportamento da
  Action; só a fronteira de injecção mudou.
- **Bump do guzzlehttp/guzzle** (commit `1ccb0f4`) incluído na mesma branch por conveniência —
  correcção de segurança (3 advisories médios, WRN-039) sem relação funcional com a issue #99.

## Aprendizagens

- **"Sem substituição prevista" não é uma regra estática — depende da testabilidade exigida.** A
  convenção do projecto diz para injectar classes concretas quando não há substituição prevista em
  produção. Mas aqui a substituição não é para produção, é para **testar uma guarda de futuro**
  sem tocar no esquema real. A pergunta certa não é "esta classe muda de implementação em
  produção?" mas "preciso de dupla-personalidade em testes vs produção?" — se sim, a interface é o
  mecanismo, mesmo com uma única implementação real.
- **Hard-delete sobre `restrictOnDelete` é uma rede de segurança, não uma estratégia.** A FK da BD
  apanha o caso onde alguém esquece de repontar uma coluna que já tem `restrictOnDelete` — mas é
  cega a `nullOnDelete`/`cascadeOnDelete`. A guarda por introspecção do esquema em runtime é o que
  torna a operação genuinamente "à prova de futuro": não depende de o programador da próxima FK
  escolher o `onDelete` certo.
- **Mass update por query builder é uma escolha consciente com um custo assumido.** Ganhar RNF-03
  (repontar milhares de documentos sem os carregar em memória) custa a auditoria individual por
  documento — o `spatie/activitylog` não vê `Documento::where()->update()`. Numa arquitectura
  Vertical Slice, este tipo de trade-off fica mais visível porque a Action é o único sítio onde a
  decisão é tomada e documentada, em vez de estar espalhada por Observers genéricos.
- **A ordem "repontar antes de remover" elimina uma classe inteira de bugs.** Ao garantir que a
  repontagem acontece sempre antes do `forceDelete()`, a "rede de segurança" da FK
  `restrictOnDelete` deixa de poder disparar em condições normais — só dispara se a guarda de
  futuro tiver uma lacuna. Isto inverte a forma habitual de pensar hard-delete (normalmente "apago
  e a BD impede-me se houver referências"); aqui é "repontar torna a remoção sempre segura, e a FK
  é só o backstop do backstop".

## SYSTEM_SPEC a actualizar

- `docs/system_spec/01-features/entidade.md` — nova Action `AgruparEntidadeAction` + colaboradores
  (`InventarioReferenciasEntidadeInterface`/`InventarioReferenciasEntidade`,
  `AgrupamentoInvalidoException`), `AgruparEntidadeRequest`, novo método do Controller.
- `docs/system_spec/05-routes/entidades.md` — nova rota `POST /entidades/{principal}/agrupar-com/{secundaria}`.
- `docs/system_spec/04-infra/autorizacao.md` — nova permissão `entidades.agrupar`, matriz role→permission, migration de seed.
- `docs/system_spec/00-index.md` — contagem de Actions/rotas da feature Entidade.
- **OpenAPI** (`./openapi.yaml`) — novo path `POST /entidades/{principal}/agrupar-com/{secundaria}`.
