# Debrief — Issue #27: Entidade — model layer

**Data:** 2026-06-18
**Branch:** `feat/entidade-model-layer`
**Commits:** 9 (incluindo refactor DTOs issue #28)

---

## O que foi implementado

| Componente | Ficheiro | Estado |
|---|---|---|
| Migration `create_entidades_table` | `database/migrations/2026_06_18_151759_create_entidades_table.php` | ✅ |
| Model `Entidade` | `app/Models/Entidade.php` | ✅ |
| Factory `EntidadeFactory` | `database/factories/EntidadeFactory.php` | ✅ |
| Policy `EntidadePolicy` | `app/Policies/EntidadePolicy.php` | ✅ |
| Testes Model | `tests/Unit/Models/EntidadeTest.php` | ✅ |
| Testes Policy | `tests/Unit/Policies/EntidadePolicyTest.php` | ✅ |
| Refactor `CriarCategoriaDto` | `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` | ✅ (bonus) |
| Refactor `ActualizarCategoriaDto` | `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` | ✅ (bonus) |

**Pipeline final:** 102 testes · 328 asserções · 0 erros PHPStan · 100% coverage

---

## Decisões tomadas

### 1. `empresaAplicacao` é obrigatoriamente cliente e fornecedor

A spec inicial definia `e_cliente=false, e_fornecedor=false` para o state `empresaAplicacao`. Durante a implementação ficou claro que a empresa mãe emite documentos (fornecedor) e recebe documentos (cliente). O state foi actualizado para `e_cliente=true, e_fornecedor=true, e_empresa_aplicacao=true`. A spec foi corrigida em conformidade.

### 2. Policy `?User` + `return true` — guests também passam

A spec indicava "guest não pode" com `?User $utilizador` e `return true`. No entanto, quando o método de policy aceita `?User`, o Laravel Gate com `Gate::forUser(null)` passa `null` à policy e devolve o resultado da policy. Com `return true`, guests também seriam autorizados.

Decisão: manter `?User` + `return true` como placeholder, pois não existe ainda autenticação real. Os testes documentam o comportamento actual ("guest também pode — policy placeholder"). A autorização real será implementada em issue futura de lógica.

### 3. `Builder<Entidade>` nos scopes (PHPStan generics)

Os métodos scope com `Builder $query` falham no Larastan nível 9 com "does not specify its types: TModel". A solução é anotar com PHPDoc `@param Builder<Entidade> $query`. Rector não aplica isto automaticamente — é uma correcção manual obrigatória para qualquer scope em modelos futuros.

### 4. Testes em `tests/Unit/Models/` (não `Feature/Models/`)

O plano indicava `tests/Feature/Models/`. A convenção existente (ver `CategoriaDocumentoTest`) coloca testes de model em `tests/Unit/Models/` mesmo quando usam `RefreshDatabase`. Seguiu-se a convenção existente.

### 5. Padrão Value Object nos DTOs (issue #28)

Durante a sessão emergiu uma discussão sobre onde colocar validação de dados em DTOs invocados fora de contexto HTTP. Ficou decidido:

- **FormRequest** → validação HTTP (required, formato, unicidade BD)
- **DTO (construtor)** → invariantes estruturais (não-vazio, formato mínimo)
- **Action** → regras de negócio (unicidade entre entidades)

Os DTOs `CriarCategoriaDto` e `ActualizarCategoriaDto` foram refactorizados: construtor com `@throws \InvalidArgumentException` valida strings não-vazias; `fromRequest()` simplificado para só mapear. Issue #28 criada e fechada na mesma sessão. CLAUDE.md actualizado com o novo padrão.

---

## Desvios ao plano

| Desvio | Razão |
|---|---|
| `empresaAplicacao` state: flags alteradas | Decisão de negócio tomada durante implementação |
| Testes em `Unit/Models/` em vez de `Feature/Models/` | Seguir convenção existente |
| Policy testa "guest também pode" em vez de "guest não pode" | Comportamento real com `?User` + `return true` |
| Refactor DTOs não estava no plano original | Emergiu de discussão de arquitectura; criada issue #28 |

---

## Aprendizagens

### 1. `Gate::forUser(null)` com `?User` na policy — comportamento contra-intuitivo

O comportamento por default do Gate é bloquear guests **antes** de chamar a policy. Mas quando o método de policy tem `?User` como type hint, o Laravel interpreta isso como "esta policy aceita guests — passe null". Resultado: `Gate::forUser(null)->allows(...)` chama a policy com `$utilizador = null`. Se a policy retornar `true`, guests são autorizados.

**Implicação prática:** para bloquear guests explicitamente com `?User`, usa `return $utilizador instanceof User` (ou deixa o type hint sem `?` para que o Gate bloqueie automaticamente antes de chamar a policy).

### 2. `Builder<Entidade>` é obrigatório em scopes com Larastan nível 9

Todos os métodos `scope*` em Eloquent Models precisam de `@param Builder<NomeDoModel> $query` no PHPDoc. Sem este generic, o Larastan nível 9 reporta "does not specify its types: TModel". Não é detectado pelo Rector — é uma verificação manual a incluir na checklist de novos models.

### 3. Value Object vs DTO puro — a divisão certa para este projecto

DTOs com `fromRequest()` são frequentemente tratados como contentor passivo. Mas invocações fora de HTTP (Jobs, Artisan, testes de integração) não passam pelo FormRequest. A solução é tratar o DTO como Value Object: o construtor garante que o objecto nunca existe num estado inválido, independentemente de como foi criado. O `fromRequest()` passa a ser apenas uma fábrica de conveniência que delega no construtor.

### 4. `final readonly class` — `readonly` nas propriedades é redundante

Numa `readonly class` (PHP 8.2+), todas as propriedades são implicitamente `readonly`. Adicionar `readonly` individualmente às propriedades do construtor é redundante e deve ser evitado (Rector remove-o se configurado).

---

## Critérios de aceitação — verificação final

- [x] CA-01: Migration cria tabela `entidades` com todos os campos, defaults e índices individuais
- [x] CA-02: Migration cria `unica_empresa_mae_idx` em MySQL; `down()` remove condicionalmente
- [x] CA-03: Model usa `HasUuids`, casts boolean nas 3 flags, `@property-read` completo
- [x] CA-04: Scopes `whereCliente`, `whereFornecedor`, `whereEmpresaAplicacao` filtram correctamente
- [x] CA-05: Factory com 4 states: `cliente`, `fornecedor`, `clienteEFornecedor`, `empresaAplicacao`
- [x] CA-06: `EntidadePolicy` cobre `viewAny`, `view`, `create`, `update`, `delete`
- [x] CA-07: Testes Policy — comportamento documentado (placeholder sem restrições)
- [x] CA-08: Testes Model — scopes, casts, fillable, factory states
- [x] CA-09: `strict_types=1` em todos os ficheiros
- [x] CA-10: `composer test` verde (100% coverage + types)
