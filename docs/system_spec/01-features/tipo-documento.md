# System Spec — Feature: TipoDocumento

> `App\Features\TipoDocumento\`

CRUD de tipos de documento, associados a uma `CategoriaDocumento` (FK `id_categoria` obrigatória, `restrictOnDelete()`). Define, por tipo, que dados a IA deve extrair (`espera_*`) e a posição da empresa-mãe (`posicao_empresa_mae`). Slice auto-contida: Actions, DTOs, Controller, FormRequests e Resource co-localizados. Camada de modelo criada em #84; esta issue (#85) completa a camada de lógica.

**Fluxo de dados:**
```
HTTP Request → FormRequest (autoriza + valida, incl. withValidator() RN-02) → Controller (constrói DTO) → Action (autoriza + acede Model) → Controller (formata com Resource) → ApiResponse
```

**Decisão arquitectural:** Actions aceitam `TipoDocumento|string` — compatíveis com Route Model Binding (HTTP) e testes unitários (UUID directo). Sem Repository — CRUD simples (mesmo desvio aceite em `CategoriaDocumento`, Issue #5). Listagem usa cursor pagination. **Sem SoftDelete** — `TipoDocumento` não tem `deleted_at`; `EliminarTipoDocumentoAction` faz hard delete simples, sem `RestaurarAction`, sem `FiltroEstadoRegisto`.

**Autorização:** dupla verificação intencional — FormRequest (`Gate::authorize()`) na camada HTTP + Action (`Gate::authorize()`) na camada de lógica. `TipoDocumentoPolicy` verifica `hasPermissionTo('tipos-documento.<accao>')` (admin todas; utilizador só `.ver`). Sem `restore` (sem SoftDelete). Ver `04-infra/autorizacao.md`.

---

## Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarTiposDocumentoAction` | `App\Features\TipoDocumento\Listar` | `handle(int $perPage, CampoOrdenacaoTiposDocumento $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, CategoriaDocumento\|string\|null $idCategoria = null): CursorPaginator<int, TipoDocumento>` | Cursor pagination via `CacheServico`; `categoria` eager-loaded (`->with('categoria')`); filtro opcional `id_categoria` aplicado via `->when()` |
| `CriarTipoDocumentoAction` | `App\Features\TipoDocumento\Criar` | `handle(CriarTipoDocumentoDto): TipoDocumento` | Cria dentro de `DB::transaction()`; devolve com `categoria` eager-loaded (`->load('categoria')`); invalida cache |
| `VerTipoDocumentoAction` | `App\Features\TipoDocumento\Ver` | `handle(TipoDocumento\|string): TipoDocumento` | Resolve UUID com `findOrFail` se string; `loadMissing('categoria')`; via `CacheServico` |
| `ActualizarTipoDocumentoAction` | `App\Features\TipoDocumento\Actualizar` | `handle(TipoDocumento\|string, ActualizarTipoDocumentoDto): TipoDocumento` | Update completo (PUT semântico) dentro de `DB::transaction()` — `fill()` + `save()` + `refresh()` + `load('categoria')`; invalida cache |
| `EliminarTipoDocumentoAction` | `App\Features\TipoDocumento\Eliminar` | `handle(TipoDocumento\|string): void` | Hard delete simples (`delete()`) dentro de `DB::transaction()` — **sem** Padrão B (sem `SoftDeletes` em `TipoDocumento`); invalida cache |

---

## DTOs

`final readonly` com `fromRequest()` (array shape `@var`; `posicao_empresa_mae` via `PosicaoEmpresaMae::from()`). Padrão Value Object — construtor valida invariantes estruturais (incl. RN-02 cross-field); `fromRequest()` só mapeia.

| DTO | Namespace | Campos (tipo) | Invariantes (construtor) |
|---|---|---|---|
| `CriarTipoDocumentoDto` | `TipoDocumento\Criar` | `nome:string`, `descricao:string`, `idCategoria:string`, `posicaoEmpresaMae:PosicaoEmpresaMae`, `esperaDataDocumento:bool`, `esperaFornecedor:bool`, `esperaCliente:bool`, `esperaValor:bool` | `nome`/`descricao`/`idCategoria` não-vazios (`trim`); RN-02: pelo menos um `espera_*` `true` |
| `ActualizarTipoDocumentoDto` | `TipoDocumento\Actualizar` | idem (update completo — PUT) | idem |

> DTOs e `TipoDocumentoResource` foram criados em #84; esta issue apenas adicionou `fromRequest()`.

---

## Enums de listagem

| Classe | Namespace | Cases | Descrição |
|---|---|---|---|
| `CampoOrdenacaoTiposDocumento` | `App\Features\TipoDocumento\Listar` | `Nome = 'nome'` | Campo de ordenação da listagem |

---

## Policy

`TipoDocumentoPolicy` (`App\Policies`, criada em #84) — `final class`, `strict_types=1`. Sem método `restore` (sem SoftDelete).

| Método | Permissão |
|---|---|
| `viewAny` / `view` | `tipos-documento.ver` |
| `create` | `tipos-documento.criar` |
| `update` | `tipos-documento.actualizar` |
| `delete` | `tipos-documento.eliminar` |

---

## FormRequests

| Classe | Namespace | `authorize()` chama | `rules()` |
|---|---|---|---|
| `ListarTiposDocumentoRequest` | `Listar` | `Gate::authorize('viewAny', TipoDocumento::class)` | `per_page`, `sort`, `direction`, `cursor`, `id_categoria` (`sometimes`, `uuid`, `Rule::exists`) |
| `CriarTipoDocumentoRequest` | `Criar` | `Gate::authorize('create', TipoDocumento::class)` | ver tabela abaixo |
| `VerTipoDocumentoRequest` | `Ver` | `Gate::authorize('view', $this->route('tipos_documento'))` | `[]` |
| `ActualizarTipoDocumentoRequest` | `Actualizar` | `Gate::authorize('update', $this->route('tipos_documento'))` | idem `Criar`, com `Rule::unique(...)->ignore($uuid)` em `nome` |
| `EliminarTipoDocumentoRequest` | `Eliminar` | `Gate::authorize('delete', $this->route('tipos_documento'))` | `[]` |

**Regras de validação de `CriarTipoDocumentoRequest`/`ActualizarTipoDocumentoRequest`:**

| Campo | Regras |
|---|---|
| `nome` | `required`, `string`, `max:255`, `Rule::unique('tipos_documento', 'nome')` (`->ignore($uuid)` em Actualizar) |
| `descricao` | `required`, `string` |
| `id_categoria` | `required`, `string`, `uuid`, `Rule::exists('categorias_documento', 'id')` |
| `posicao_empresa_mae` | `required`, `string`, `Rule::in(PosicaoEmpresaMae::cases())` |
| `espera_data_documento` / `espera_fornecedor` / `espera_cliente` / `espera_valor` | `required`, `boolean` |

`nome` `Rule::unique` não estava explícito no enunciado da issue — adicionado porque `tipos_documento.nome` tem índice único na BD (#84); sem esta regra, um duplicado geraria `QueryException` (500) em vez de 422.

### `withValidator()` — validação cross-field RN-02 (primeiro uso no projecto)

`CriarTipoDocumentoRequest` e `ActualizarTipoDocumentoRequest` implementam `withValidator(Validator $validator)`:

```php
public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $validator): void {
        if (! $this->boolean('espera_data_documento') && ! $this->boolean('espera_fornecedor')
            && ! $this->boolean('espera_cliente') && ! $this->boolean('espera_valor')) {
            $validator->errors()->add('espera_data_documento', 'Pelo menos um dos campos espera_* tem de ser verdadeiro.');
        }
    });
}
```

- Lê `$this->boolean(...)` directamente do request (**não** `validated()`) — evita depender de chaves que podem estar ausentes se um campo individual já falhou outra regra; `boolean()` tem fallback `false` para chave ausente.
- Erro anexado ao campo `espera_data_documento` — devolve 422 estruturado (Problem Details, `02-shared/http.md`) em vez de deixar propagar a `\InvalidArgumentException` do construtor do DTO (que resultaria em 500 se não interceptada em HTTP).
- **Duplicação intencional com o DTO** — RN-02 é validada aqui (HTTP, 422 amigável) **e** no construtor do DTO (`\InvalidArgumentException`, protege contextos não-HTTP: Jobs, Artisan, testes directos à Action). As duas implementações não derivam uma da outra — têm de ser mantidas em sincronia manualmente se a regra mudar.

`id_categoria` no filtro de `ListarTiposDocumentoRequest` usa `sometimes` + `Rule::exists` em simultâneo (não `sometimes` isolado) — omitido: sem filtro aplicado; fornecido: tem de corresponder a uma `CategoriaDocumento` existente, senão 422 (mesma assimetria já usada em `estado`/`Rule::in` de `ListarCategoriasRequest`).

---

## Controller

`TipoDocumentoController` (`App\Features\TipoDocumento`) — `final`, sem lógica. Route Model Binding (`TipoDocumento $tipos_documento`) + injecção de Actions via parâmetros de método.

| Método | FormRequest | Action invocada |
|---|---|---|
| `index` | `ListarTiposDocumentoRequest` | `ListarTiposDocumentoAction::handle()` — extrai `per_page`, `sort`, `direction`, `id_categoria`; devolve via `ApiResponse::devolverPaginado()` |
| `store` | `CriarTipoDocumentoRequest` | `CriarTipoDocumentoAction::handle()` — devolve via `ApiResponse::devolverCriado()` |
| `show` | `VerTipoDocumentoRequest` | `VerTipoDocumentoAction::handle()` |
| `update` | `ActualizarTipoDocumentoRequest` | `ActualizarTipoDocumentoAction::handle()` |
| `destroy` | `EliminarTipoDocumentoRequest` | `EliminarTipoDocumentoAction::handle()` — devolve via `ApiResponse::devolverVazio()` |

Sem método `restaurar` (sem SoftDelete).

---

## Resource

`TipoDocumentoResource` — `App\Features\TipoDocumento\TipoDocumentoResource` (criado em #84)

```json
{
  "id": "019741b2-...",
  "nome": "Fatura de Fornecedor",
  "descricao": "...",
  "categoria": { "id": "...", "nome": "...", "slug": "...", "tipo_movimento": "debito" },
  "tipo_movimento": "debito",
  "posicao_empresa_mae": "cliente",
  "espera_data_documento": true,
  "espera_fornecedor": true,
  "espera_cliente": false,
  "espera_valor": true,
  "criado_em": "2026-07-10T10:00:00+00:00",
  "actualizado_em": "2026-07-10T10:00:00+00:00"
}
```

- `categoria` via `whenLoaded('categoria')` — omitido silenciosamente se a relação não for eager-loaded pela Action (RN-03)
- `tipo_movimento` sempre derivado de `$this->categoria?->tipo_movimento?->value` (acesso directo, não `whenLoaded`) — nunca coluna própria
- **Risco (Brief #85):** se uma Action esquecer `->with('categoria')`/`->load('categoria')`/`loadMissing('categoria')`, o Resource não falha — omite `categoria` e `tipo_movimento` fica `null`. Larastan não apanha; só teste de feature que inspecciona o payload apanha a omissão (CA-16).

---

## Integração com `CategoriaDocumento` (CA-13)

`tipos_documento.id_categoria` é `restrictOnDelete()` (#84). `EliminarCategoriaAction` (Padrão B, `02-shared/soft-delete.md`) já cobre este caso sem alteração de código: ao tentar `forceDelete()` uma `CategoriaDocumento` com `TipoDocumento` associado, a `QueryException` da FK é apanhada e a categoria cai em soft delete. Teste de integração em `tests/Feature/Features/TipoDocumento/EliminarCategoriaComTipoDocumentoTest.php` prova este comportamento.
