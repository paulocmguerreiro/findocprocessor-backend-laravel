<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function criarAdmin(): User
{
    $utilizador = User::factory()->create();
    $utilizador->assignRole('admin');

    return $utilizador;
}

function criarUtilizador(): User
{
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');

    return $utilizador;
}

function criarEAutenticarAdmin(): User
{
    $utilizador = criarAdmin();
    Sanctum::actingAs($utilizador, ['api']);

    return $utilizador;
}

function criarEAutenticarUtilizador(): User
{
    $utilizador = criarUtilizador();
    Sanctum::actingAs($utilizador, ['api']);

    return $utilizador;
}

function criarEAutenticarSemRole(): User
{
    $utilizador = User::factory()->create();
    Sanctum::actingAs($utilizador, ['api']);

    return $utilizador;
}
