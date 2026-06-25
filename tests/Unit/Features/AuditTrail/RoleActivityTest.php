<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Activity::query()->delete());

it('regista actividade created ao criar role', function (): void {
    $role = Role::create(['name' => 'gestor']);

    $actividade = Activity::query()->first();

    expect(Activity::count())->toBe(1)
        ->and($actividade->event)->toBe('created')
        ->and($actividade->subject_type)->toBe(Role::class)
        ->and((int) $actividade->subject_id)->toBe($role->id);
});

it('regista actividade updated com valores antigos e novos', function (): void {
    $role = Role::create(['name' => 'gestor']);
    Activity::query()->delete();

    $role->update(['name' => 'gestor-senior']);

    $actividade = Activity::query()->first();

    expect(Activity::count())->toBe(1)
        ->and($actividade->event)->toBe('updated')
        ->and($actividade->properties->get('attributes')['name'])->toBe('gestor-senior')
        ->and($actividade->properties->get('old')['name'])->toBe('gestor');
});

it('não regista actividade quando update não altera nada', function (): void {
    $role = Role::create(['name' => 'gestor']);
    Activity::query()->delete();

    $role->save();

    expect(Activity::count())->toBe(0);
});

it('regista actividade deleted ao eliminar role', function (): void {
    $role = Role::create(['name' => 'gestor']);
    Activity::query()->delete();

    $role->delete();

    expect(Activity::count())->toBe(1)
        ->and(Activity::query()->first()->event)->toBe('deleted');
});

it('faz rollback do log quando excepção ocorre na transação', function (): void {
    Role::created(function (): void {
        throw new RuntimeException('falha simulada após insert');
    });

    try {
        DB::transaction(fn () => Role::create(['name' => 'gestor']));
    } catch (RuntimeException) {
        // esperado — a transação faz rollback
    }

    expect(Activity::count())->toBe(0);
});
