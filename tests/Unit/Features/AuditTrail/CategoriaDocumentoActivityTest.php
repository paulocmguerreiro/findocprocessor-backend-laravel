<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Os seeders criam roles via Eloquent na migração, o que gera actividade
// persistente fora da transação do teste — limpar antes de cada cenário.
beforeEach(fn () => Activity::query()->delete());

it('regista actividade created ao criar', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $actividade = Activity::query()->first();

    expect(Activity::count())->toBe(1)
        ->and($actividade->event)->toBe('created')
        ->and($actividade->subject_type)->toBe(CategoriaDocumento::class)
        ->and($actividade->subject_id)->toBe($categoria->id);
});

it('regista actividade updated com valores antigos e novos', function (): void {
    $categoria = CategoriaDocumento::factory()->create(['nome' => 'Original']);
    Activity::query()->delete();

    $categoria->update(['nome' => 'Alterado']);

    $actividade = Activity::query()->first();

    expect(Activity::count())->toBe(1)
        ->and($actividade->event)->toBe('updated')
        ->and($actividade->properties->get('attributes')['nome'])->toBe('Alterado')
        ->and($actividade->properties->get('old')['nome'])->toBe('Original');
});

it('não regista actividade quando update não altera nada', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    Activity::query()->delete();

    $categoria->save();

    expect(Activity::count())->toBe(0);
});

it('regista actividade deleted ao eliminar', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    Activity::query()->delete();

    $categoria->delete();

    expect(Activity::count())->toBe(1)
        ->and(Activity::query()->first()->event)->toBe('deleted');
});

it('faz rollback do log quando excepção ocorre na transação', function (): void {
    CategoriaDocumento::created(function (): void {
        throw new RuntimeException('falha simulada após insert');
    });

    try {
        DB::transaction(fn () => CategoriaDocumento::factory()->create());
    } catch (RuntimeException) {
        // esperado — a transação faz rollback
    }

    expect(Activity::count())->toBe(0);
});
