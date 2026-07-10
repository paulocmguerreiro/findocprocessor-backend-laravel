<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Activity::query()->delete());

describe('autenticado', function (): void {
    beforeEach(function (): void {
        criarEAutenticarAdmin();
        Activity::query()->delete();
    });

    it('cria tipo de documento e devolve 201 com o recurso, incluindo categoria', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $payload = [
            'nome' => 'Fatura Fornecedor',
            'descricao' => 'Fatura emitida por um fornecedor',
            'id_categoria' => $categoria->id,
            'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente->value,
            'espera_data_documento' => true,
            'espera_fornecedor' => true,
            'espera_cliente' => false,
            'espera_valor' => true,
        ];

        $this->postJson('/api/tipos-documento', $payload)
            ->assertCreated()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', fn (AssertableJson $data): AssertableJson => $data
                    ->whereType('id', 'string')
                    ->where('nome', 'Fatura Fornecedor')
                    ->where('posicao_empresa_mae', PosicaoEmpresaMae::Cliente->value)
                    ->where('categoria.id', $categoria->id)
                    ->etc()
                )
            );

        $this->assertDatabaseHas('tipos_documento', ['nome' => 'Fatura Fornecedor']);
    });

    it('devolve 422 quando campos obrigatórios estão em falta', function (): void {
        $this->postJson('/api/tipos-documento', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['status', 'detail', 'errors' => ['nome', 'descricao', 'id_categoria', 'posicao_empresa_mae', 'espera_data_documento', 'espera_fornecedor', 'espera_cliente', 'espera_valor']]);
    });

    it('devolve 422 quando id_categoria não existe', function (): void {
        $this->postJson('/api/tipos-documento', [
            'nome' => 'Fatura Fornecedor',
            'descricao' => 'Fatura emitida por um fornecedor',
            'id_categoria' => (string) Str::uuid7(),
            'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente->value,
            'espera_data_documento' => true,
            'espera_fornecedor' => true,
            'espera_cliente' => false,
            'espera_valor' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_categoria']);
    });

    it('devolve 422 quando os 4 espera_* são todos false', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $this->postJson('/api/tipos-documento', [
            'nome' => 'Fatura Fornecedor',
            'descricao' => 'Fatura emitida por um fornecedor',
            'id_categoria' => $categoria->id,
            'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente->value,
            'espera_data_documento' => false,
            'espera_fornecedor' => false,
            'espera_cliente' => false,
            'espera_valor' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['espera_data_documento']);
    });

    it('devolve 422 quando o nome já existe', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        TipoDocumento::factory()->for($categoria, 'categoria')->create(['nome' => 'Fatura Fornecedor']);

        $this->postJson('/api/tipos-documento', [
            'nome' => 'Fatura Fornecedor',
            'descricao' => 'Outra descrição',
            'id_categoria' => $categoria->id,
            'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente->value,
            'espera_data_documento' => true,
            'espera_fornecedor' => true,
            'espera_cliente' => false,
            'espera_valor' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome']);
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();
    Activity::query()->delete();
    $categoria = CategoriaDocumento::factory()->create();

    $this->postJson('/api/tipos-documento', [
        'nome' => 'Fatura Fornecedor',
        'descricao' => 'Fatura emitida por um fornecedor',
        'id_categoria' => $categoria->id,
        'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente->value,
        'espera_data_documento' => true,
        'espera_fornecedor' => true,
        'espera_cliente' => false,
        'espera_valor' => true,
    ])->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $this->postJson('/api/tipos-documento', [
        'nome' => 'Fatura Fornecedor',
        'descricao' => 'Fatura emitida por um fornecedor',
        'id_categoria' => $categoria->id,
        'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente->value,
        'espera_data_documento' => true,
        'espera_fornecedor' => true,
        'espera_cliente' => false,
        'espera_valor' => true,
    ])->assertUnauthorized();
});
