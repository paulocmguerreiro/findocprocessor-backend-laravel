<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('GET /categorias-documento sem token devolve 401', function (): void {
    $this->getJson('/api/categorias-documento')
        ->assertUnauthorized();
});

it('GET /categorias-documento com token válido devolve 200', function (): void {
    $utilizador = User::factory()->create();
    $utilizador->assignRole('admin');
    Sanctum::actingAs($utilizador, ['api']);

    $this->getJson('/api/categorias-documento')
        ->assertOk();
});
