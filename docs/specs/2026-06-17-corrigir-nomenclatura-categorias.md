# Spec — Issue #22: Corrigir nomenclatura CategoriaDocumento

**Data:** 2026-06-17
**Brief:** `docs/briefs/2026-06-17-corrigir-nomenclatura-categorias.md`

---

## CA-01 — Propriedade `$tipoMovimento` nos DTOs

### `CriarCategoriaDto`

**Antes:**
```php
public function __construct(
    public string $nome,
    public string $slug,
    public TipoMovimento $tipo_movimento,
) {}

return new self(
    nome: $nome,
    slug: $slug,
    tipo_movimento: TipoMovimento::from($tipoMovimento),
);
```

**Depois:**
```php
public function __construct(
    public string $nome,
    public string $slug,
    public TipoMovimento $tipoMovimento,
) {}

return new self(
    nome: $nome,
    slug: $slug,
    tipoMovimento: TipoMovimento::from($tipoMovimento),
);
```

### `ActualizarCategoriaDto`

**Antes:**
```php
public function __construct(
    public ?string $nome,
    public ?string $slug,
    public ?TipoMovimento $tipo_movimento,
) {}

return new self(
    nome: $nome,
    slug: $slug,
    tipo_movimento: is_string($tipoMovimento) ? TipoMovimento::from($tipoMovimento) : null,
);
```

**Depois:**
```php
public function __construct(
    public ?string $nome,
    public ?string $slug,
    public ?TipoMovimento $tipoMovimento,
) {}

return new self(
    nome: $nome,
    slug: $slug,
    tipoMovimento: is_string($tipoMovimento) ? TipoMovimento::from($tipoMovimento) : null,
);
```

### Acesso à propriedade nas Actions

`CriarCategoriaAction::handle()`:
```php
// antes
'tipo_movimento' => $dados->tipo_movimento,
// depois
'tipo_movimento' => $dados->tipoMovimento,
// nota: chave 'tipo_movimento' mantém-se (coluna BD)
```

`ActualizarCategoriaAction::handle()`:
```php
// antes
'tipo_movimento' => $dados->tipo_movimento,
// depois
'tipo_movimento' => $dados->tipoMovimento,
// nota: chave 'tipo_movimento' mantém-se (coluna BD)
```

---

## CA-02 — Variáveis contextuais em vez de `$validated`

### DTOs — `$dadosValidados`

**Antes:**
```php
/** @var array{nome: string, slug: string, tipo_movimento: string} $validated */
$validated = $request->validated();
$nome = $validated['nome'] ?? null;
$slug = $validated['slug'] ?? null;
$tipoMovimento = $validated['tipo_movimento'] ?? null;
```

**Depois:**
```php
/** @var array{nome: string, slug: string, tipo_movimento: string} $dadosValidados */
$dadosValidados = $request->validated();
$nome = $dadosValidados['nome'] ?? null;
$slug = $dadosValidados['slug'] ?? null;
$tipoMovimento = $dadosValidados['tipo_movimento'] ?? null;
```

Aplica-se a `CriarCategoriaDto::fromRequest()` e `ActualizarCategoriaDto::fromRequest()`.

### Controller — `$parametrosValidados`

`CategoriaDocumentoController::index()`:

**Antes:**
```php
/** @var array{per_page?: string, sort?: string, direction?: string} $validated */
$validated = $pedido->validated();
$porPagina = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
$campoOrdenacao = CampoOrdenacaoCategorias::from($validated['sort'] ?? ...);
$direcaoOrdenacao = DirecaoOrdenacao::from($validated['direction'] ?? ...);
```

**Depois:**
```php
/** @var array{per_page?: string, sort?: string, direction?: string} $parametrosValidados */
$parametrosValidados = $pedido->validated();
$porPagina = isset($parametrosValidados['per_page']) ? (int) $parametrosValidados['per_page'] : 15;
$campoOrdenacao = CampoOrdenacaoCategorias::from($parametrosValidados['sort'] ?? ...);
$direcaoOrdenacao = DirecaoOrdenacao::from($parametrosValidados['direction'] ?? ...);
```

---

## CA-03 — `$camposParaActualizar` em `ActualizarCategoriaAction`

**Antes:**
```php
$campos = array_filter([
    'nome' => $dados->nome,
    'slug' => $dados->slug,
    'tipo_movimento' => $dados->tipo_movimento,
], fn (mixed $valor): bool => $valor !== null);

$categoria->fill($campos)->save();
```

**Depois:**
```php
$camposParaActualizar = array_filter([
    'nome' => $dados->nome,
    'slug' => $dados->slug,
    'tipo_movimento' => $dados->tipoMovimento,
], fn (mixed $valor): bool => $valor !== null);

$categoria->fill($camposParaActualizar)->save();
```

---

## CA-04 — Parâmetro `$pedido` no Controller

`store()` e `update()`:

**Antes:**
```php
public function store(CriarCategoriaRequest $request, CriarCategoriaAction $accao): JsonResponse
{
    $categoria = $accao->handle(CriarCategoriaDto::fromRequest($request));
    ...
}

public function update(ActualizarCategoriaRequest $request, CategoriaDocumento $categorias_documento, ActualizarCategoriaAction $accao): JsonResponse
{
    $categoria = $accao->handle($categorias_documento, ActualizarCategoriaDto::fromRequest($request));
    ...
}
```

**Depois:**
```php
public function store(CriarCategoriaRequest $pedido, CriarCategoriaAction $accao): JsonResponse
{
    $categoria = $accao->handle(CriarCategoriaDto::fromRequest($pedido));
    ...
}

public function update(ActualizarCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, ActualizarCategoriaAction $accao): JsonResponse
{
    $categoria = $accao->handle($categorias_documento, ActualizarCategoriaDto::fromRequest($pedido));
    ...
}
```

---

## CA-05 — Testes: named arguments actualizados

`tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php` (2 ocorrências):

**Antes:**
```php
$dto = new ActualizarCategoriaDto(nome: 'Actualizado', slug: null, tipo_movimento: null);
```

**Depois:**
```php
$dto = new ActualizarCategoriaDto(nome: 'Actualizado', slug: null, tipoMovimento: null);
```

---

## Invariantes (não mudam em nenhuma circunstância)

| O que não muda | Motivo |
|----------------|--------|
| Chaves `'tipo_movimento'` nos arrays `create()` e `fill()` | Nome de coluna da BD (snake_case) |
| Parâmetro `$categorias_documento` no Controller | Route model binding (imposto pelo framework) |
| Métodos `handle()`, `store()`, `update()`, etc. | Impostos pelo framework |
| Validação e regras nos FormRequests | Sem alteração de lógica |
| Respostas da API | Zero impacto no contrato externo |
