<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaAction;
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaDto;
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

it('actualiza quando recebe CategoriaDocumento directamente', function (): void {
    $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->create(['nome' => 'Original']);

    $dto = new ActualizarCategoriaDto(
        nome: 'Actualizado',
        slug: 'actualizado',
        tipoMovimento: TipoMovimento::Credito,
    );
    $resultado = (new ActualizarCategoriaAction)->handle($categoria, $dto);

    expect($resultado->nome)->toBe('Actualizado')
        ->and($resultado->slug)->toBe('actualizado')
        ->and($resultado->tipo_movimento)->toBe(TipoMovimento::Credito);
});

it('actualiza quando recebe string UUID', function (): void {
    $categoria = CategoriaDocumento::factory()->comMovimentoCredito()->create(['nome' => 'Original']);

    $dto = new ActualizarCategoriaDto(
        nome: 'Actualizado',
        slug: 'actualizado',
        tipoMovimento: TipoMovimento::Debito,
    );
    $resultado = (new ActualizarCategoriaAction)->handle($categoria->id, $dto);

    expect($resultado->nome)->toBe('Actualizado')
        ->and($resultado->slug)->toBe('actualizado')
        ->and($resultado->tipo_movimento)->toBe(TipoMovimento::Debito);
});

it('faz rollback quando ocorre excepção durante update', function (): void {
    $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->create(['nome' => 'Original', 'slug' => 'original']);

    CategoriaDocumento::saved(function (): void {
        throw new RuntimeException('falha simulada durante update');
    });

    $dto = new ActualizarCategoriaDto(
        nome: 'Alterado',
        slug: 'alterado',
        tipoMovimento: TipoMovimento::Credito,
    );

    expect(fn (): CategoriaDocumento => (new ActualizarCategoriaAction)->handle($categoria, $dto))
        ->toThrow(RuntimeException::class, 'falha simulada durante update');

    $this->assertDatabaseHas('categorias_documento', ['id' => $categoria->id, 'nome' => 'Original', 'slug' => 'original']);
});

it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    $this->actingAs($utilizador);

    $dto = new ActualizarCategoriaDto(nome: 'Alterado', slug: 'alterado', tipoMovimento: TipoMovimento::Neutro);

    expect(fn () => (new ActualizarCategoriaAction)->handle($categoria, $dto))
        ->toThrow(AuthorizationException::class);
});
