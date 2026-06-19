# Plano — Issue #41: ListarCategoriasActionTest (Unit)

**Data:** 2026-06-19
**Issue:** #41
**Slug:** test-listar-categorias-action-unit

---

## Tarefas

### T1 — Criar `ListarCategoriasActionTest.php`

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php`

Criar o ficheiro com os 3 testes especificados, por analogia directa com `tests/Unit/Features/Entidade/ListarEntidadesActionTest.php`.

```php
<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Listar\CampoOrdenacaoCategorias;
use App\Features\CategoriaDocumento\Listar\ListarCategoriasAction;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve lista vazia quando não existem categorias', function (): void {
    $resultado = (new ListarCategoriasAction)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc);

    expect($resultado->count())->toBe(0);
});

it('devolve categorias ordenadas por nome ascendente', function (): void {
    CategoriaDocumento::factory()->create(['nome' => 'Zeta']);
    CategoriaDocumento::factory()->create(['nome' => 'Alfa']);
    CategoriaDocumento::factory()->create(['nome' => 'Beta']);

    $resultado = (new ListarCategoriasAction)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc);
    $nomes = $resultado->pluck('nome')->all();

    expect($nomes)->toBe(['Alfa', 'Beta', 'Zeta']);
});

it('respeita o per_page na paginação por cursor', function (): void {
    CategoriaDocumento::factory()->count(5)->create();

    $resultado = (new ListarCategoriasAction)->handle(2, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc);

    expect($resultado->count())->toBe(2)
        ->and($resultado->nextCursor())->not->toBeNull();
});
```

### T2 — Executar `composer test` e corrigir erros

Verificar que a pipeline completa passa a verde (lint, types, coverage).

### T3 — Commit

```bash
git add tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php
git commit -m "test(categorias-documento): ListarCategoriasActionTest — dual pattern unit"
```

---

## Ordem de execução

T1 → T2 → T3

## Estimativa

~15 minutos (ficheiro simples por analogia directa).
