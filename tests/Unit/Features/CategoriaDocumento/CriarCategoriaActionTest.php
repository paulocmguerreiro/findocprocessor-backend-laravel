<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Criar\CriarCategoriaAction;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaDto;
use App\Models\CategoriaDocumento;
use App\Models\User;
use App\Shared\Enums\TipoMovimento;
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

it('cria categoria com dados válidos', function (): void {
    $dto = new CriarCategoriaDto(
        nome: 'Fornecedores',
        slug: 'fornecedores',
        tipoMovimento: TipoMovimento::Debito,
    );

    $resultado = (new CriarCategoriaAction)->handle($dto);

    expect($resultado->nome)->toBe('Fornecedores')
        ->and($resultado->slug)->toBe('fornecedores')
        ->and($resultado->tipo_movimento)->toBe(TipoMovimento::Debito);

    $this->assertDatabaseHas('categorias_documento', ['slug' => 'fornecedores']);
});

it('faz rollback quando ocorre excepção após insert', function (): void {
    CategoriaDocumento::created(function (): void {
        throw new RuntimeException('falha simulada após insert');
    });

    $dto = new CriarCategoriaDto(
        nome: 'Fornecedores',
        slug: 'fornecedores',
        tipoMovimento: TipoMovimento::Debito,
    );

    expect(fn (): CategoriaDocumento => (new CriarCategoriaAction)->handle($dto))
        ->toThrow(RuntimeException::class, 'falha simulada após insert');

    $this->assertDatabaseCount('categorias_documento', 0);
});

it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    $this->actingAs($utilizador);

    $dto = new CriarCategoriaDto(
        nome: 'Fornecedores',
        slug: 'fornecedores',
        tipoMovimento: TipoMovimento::Debito,
    );

    expect(fn () => (new CriarCategoriaAction)->handle($dto))
        ->toThrow(AuthorizationException::class);
});
