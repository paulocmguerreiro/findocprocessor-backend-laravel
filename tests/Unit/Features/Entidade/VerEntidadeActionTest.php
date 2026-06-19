<?php

declare(strict_types=1);

use App\Features\Entidade\Ver\VerEntidadeAction;
use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

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
