<?php

declare(strict_types=1);

use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(fn () => criarEAutenticarAdmin());

it('regista inicio e fim ao criar categoria', function (): void {
    Log::spy();

    $this->postJson('/api/categorias-documento', [
        'nome' => 'Fatura de Teste',
        'slug' => 'fatura-de-teste',
        'tipo_movimento' => TipoMovimento::Debito->value,
    ])->assertCreated();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $msg): bool => $msg === 'categoria.criar.inicio');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $msg): bool => $msg === 'categoria.criar.fim');
});
