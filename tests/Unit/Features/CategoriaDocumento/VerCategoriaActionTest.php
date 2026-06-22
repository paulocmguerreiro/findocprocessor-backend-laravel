<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Ver\VerCategoriaAction;
use App\Models\CategoriaDocumento;
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

it('devolve o modelo quando recebe CategoriaDocumento directamente', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $resultado = (new VerCategoriaAction)->handle($categoria);

    expect($resultado)->toBe($categoria);
});

it('resolve o modelo quando recebe string UUID', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $resultado = (new VerCategoriaAction)->handle($categoria->id);

    expect($resultado->id)->toBe($categoria->id);
});

it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    $utilizador = User::factory()->create(); // sem role — sem categorias-documento.ver
    $this->actingAs($utilizador);

    expect(fn (): CategoriaDocumento => (new VerCategoriaAction)->handle($categoria))
        ->toThrow(AuthorizationException::class);
});
