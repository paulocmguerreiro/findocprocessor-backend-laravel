<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('GET /categorias-documento sem token devolve 401', function (): void {
    $this->getJson('/api/categorias-documento')
        ->assertUnauthorized();
});

it('GET /categorias-documento com token válido devolve 200', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['api']);

    $this->getJson('/api/categorias-documento')
        ->assertOk();
});
