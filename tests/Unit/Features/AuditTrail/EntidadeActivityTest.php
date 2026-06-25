<?php

declare(strict_types=1);

use App\Models\Entidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Activity::query()->delete());

it('regista actividade created ao criar', function (): void {
    $entidade = Entidade::factory()->create();

    $actividade = Activity::query()->first();

    expect(Activity::count())->toBe(1)
        ->and($actividade->event)->toBe('created')
        ->and($actividade->subject_type)->toBe(Entidade::class)
        ->and($actividade->subject_id)->toBe($entidade->id);
});

it('regista actividade updated com valores antigos e novos', function (): void {
    $entidade = Entidade::factory()->create(['nome' => 'Original']);
    Activity::query()->delete();

    $entidade->update(['nome' => 'Alterado']);

    $actividade = Activity::query()->first();

    expect(Activity::count())->toBe(1)
        ->and($actividade->event)->toBe('updated')
        ->and($actividade->properties->get('attributes')['nome'])->toBe('Alterado')
        ->and($actividade->properties->get('old')['nome'])->toBe('Original');
});

it('não regista actividade quando update não altera nada', function (): void {
    $entidade = Entidade::factory()->create();
    Activity::query()->delete();

    $entidade->save();

    expect(Activity::count())->toBe(0);
});

it('regista actividade deleted ao eliminar', function (): void {
    $entidade = Entidade::factory()->create();
    Activity::query()->delete();

    $entidade->delete();

    expect(Activity::count())->toBe(1)
        ->and(Activity::query()->first()->event)->toBe('deleted');
});

it('faz rollback do log quando excepção ocorre na transação', function (): void {
    Entidade::created(function (): void {
        throw new RuntimeException('falha simulada após insert');
    });

    try {
        DB::transaction(fn () => Entidade::factory()->create());
    } catch (RuntimeException) {
        // esperado — a transação faz rollback
    }

    expect(Activity::count())->toBe(0);
});

it('nunca regista o campo sensível nif (logExcept)', function (): void {
    $entidade = Entidade::factory()->create(['nif' => '123456789', 'nome' => 'Original']);

    $actividadeCriacao = Activity::query()->first();
    expect($actividadeCriacao->properties->get('attributes'))->not->toHaveKey('nif');

    Activity::query()->delete();
    $entidade->update(['nif' => '987654321', 'nome' => 'Alterado']);

    $actividadeUpdate = Activity::query()->first();
    expect($actividadeUpdate->properties->get('attributes'))->not->toHaveKey('nif')
        ->and($actividadeUpdate->properties->get('old'))->not->toHaveKey('nif');
});
