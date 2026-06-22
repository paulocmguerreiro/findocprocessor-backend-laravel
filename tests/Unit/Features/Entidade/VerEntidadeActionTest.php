<?php

declare(strict_types=1);

use App\Features\Entidade\Ver\VerEntidadeAction;
use App\Models\Entidade;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('admin');
    $this->actingAs($utilizador);
});

it('devolve o modelo quando recebe Entidade directamente', function (): void {
    $entidade = Entidade::factory()->create();

    $resultado = (new VerEntidadeAction)->handle($entidade);

    expect($resultado)->toBe($entidade);
});

it('resolve o modelo quando recebe string UUID', function (): void {
    $entidade = Entidade::factory()->create();

    $resultado = (new VerEntidadeAction)->handle($entidade->id);

    expect($resultado->id)->toBe($entidade->id);
});

it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
    $entidade = Entidade::factory()->create();
    $utilizador = User::factory()->create(); // sem role — sem entidades.ver
    $this->actingAs($utilizador);

    expect(fn () => (new VerEntidadeAction)->handle($entidade))
        ->toThrow(AuthorizationException::class);
});
