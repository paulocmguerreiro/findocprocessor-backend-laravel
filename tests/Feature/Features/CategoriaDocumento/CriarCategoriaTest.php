<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\User;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Os seeds de roles deixam actividade persistente fora da transação do teste.
beforeEach(fn () => Activity::query()->delete());

describe('autenticado', function (): void {
    beforeEach(function (): void {
        criarEAutenticarAdmin();
        // O User passou a registar actividade; limpar o evento 'created' do
        // admin isola a contagem à actividade gerada pelo próprio pedido.
        Activity::query()->delete();
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
                    ->where('deleted_at', null)
                )
            );

        $this->assertDatabaseHas('categorias_documento', ['slug' => 'fatura-de-fornecedor']);

        expect(Activity::count())->toBe(1)
            ->and(Activity::query()->first()->event)->toBe('created')
            ->and(Activity::query()->first()->causer_id)->not->toBeNull();
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
    criarEAutenticarUtilizador();
    Activity::query()->delete();

    $this->postJson('/api/categorias-documento', [
        'nome' => 'Categoria Utilizador',
        'slug' => 'categoria-utilizador',
        'tipo_movimento' => TipoMovimento::Neutro->value,
    ])->assertForbidden();

    expect(Activity::count())->toBe(0);
});

it('guest sem token recebe 401', function (): void {
    $this->postJson('/api/categorias-documento', [
        'nome' => 'Categoria Guest',
        'slug' => 'categoria-guest',
        'tipo_movimento' => TipoMovimento::Neutro->value,
    ])->assertUnauthorized();
});
