<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\User;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

describe('autenticado', function (): void {
    beforeEach(function (): void {
        $utilizador = User::factory()->create();
        $utilizador->assignRole('admin');
        Sanctum::actingAs($utilizador, ['api']);
    });

    it('cria categoria e devolve 201 com o recurso', function (): void {
        $payload = [
            'nome' => 'Fatura de Fornecedor',
            'slug' => 'fatura-de-fornecedor',
            'tipo_movimento' => TipoMovimento::Debito->value,
        ];

        $this->postJson('/api/categorias-documento', $payload)
            ->assertCreated()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', fn (AssertableJson $data): AssertableJson => $data
                    ->whereType('id', 'string')
                    ->where('nome', 'Fatura de Fornecedor')
                    ->where('slug', 'fatura-de-fornecedor')
                    ->where('tipo_movimento', TipoMovimento::Debito->value)
                )
            );

        $this->assertDatabaseHas('categorias_documento', ['slug' => 'fatura-de-fornecedor']);
    });

    it('devolve 422 quando o slug já existe', function (): void {
        CategoriaDocumento::factory()->create(['slug' => 'slug-duplicado']);

        $this->postJson('/api/categorias-documento', [
            'nome' => 'Outra Categoria',
            'slug' => 'slug-duplicado',
            'tipo_movimento' => TipoMovimento::Credito->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['status', 'detail', 'errors' => ['slug']]);
    });

    it('devolve 422 quando o tipo_movimento é inválido', function (): void {
        $this->postJson('/api/categorias-documento', [
            'nome' => 'Categoria Inválida',
            'slug' => 'categoria-invalida',
            'tipo_movimento' => 'invalido',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['status', 'detail', 'errors' => ['tipo_movimento']]);
    });

    it('devolve 422 quando campos obrigatórios estão em falta', function (): void {
        $this->postJson('/api/categorias-documento', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['status', 'detail', 'errors' => ['nome', 'slug', 'tipo_movimento']]);
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    Sanctum::actingAs($utilizador, ['api']);

    $this->postJson('/api/categorias-documento', [
        'nome' => 'Categoria Utilizador',
        'slug' => 'categoria-utilizador',
        'tipo_movimento' => TipoMovimento::Neutro->value,
    ])->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $this->postJson('/api/categorias-documento', [
        'nome' => 'Categoria Guest',
        'slug' => 'categoria-guest',
        'tipo_movimento' => TipoMovimento::Neutro->value,
    ])->assertUnauthorized();
});
