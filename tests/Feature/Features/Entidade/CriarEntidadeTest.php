<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    it('cria entidade e devolve 201 com o recurso', function (): void {
        $payload = [
            'nome' => 'Empresa Teste',
            'nif' => '501234567',
            'e_cliente' => true,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => false,
        ];

        $this->postJson('/api/entidades', $payload)
            ->assertCreated()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', fn (AssertableJson $data): AssertableJson => $data
                    ->whereType('id', 'string')
                    ->where('nome', 'Empresa Teste')
                    ->where('nif', '501234567')
                    ->where('e_cliente', true)
                    ->where('e_fornecedor', false)
                    ->where('e_empresa_aplicacao', false)
                    ->where('deleted_at', null)
                )
            );

        $this->assertDatabaseHas('entidades', ['nif' => '501234567']);

        $actividade = Activity::query()->first();
        expect(Activity::count())->toBe(1)
            ->and($actividade->event)->toBe('created')
            ->and($actividade->properties->get('attributes'))->not->toHaveKey('nif');
    });

    it('cria entidade como empresa mãe e força e_cliente e e_fornecedor a true', function (): void {
        $empresaAnterior = Entidade::factory()->empresaAplicacao()->create();

        $this->postJson('/api/entidades', [
            'nome' => 'Nova Empresa Mãe',
            'nif' => '500000001',
            'e_cliente' => false,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => true,
        ])->assertCreated()
            ->assertJsonPath('data.e_empresa_aplicacao', true)
            ->assertJsonPath('data.e_cliente', true)
            ->assertJsonPath('data.e_fornecedor', true);

        $this->assertDatabaseHas('entidades', ['nif' => '500000001', 'e_empresa_aplicacao' => true]);
        $this->assertDatabaseHas('entidades', ['id' => $empresaAnterior->id, 'e_empresa_aplicacao' => false]);
    });

    it('devolve 422 quando campos obrigatórios estão em falta', function (): void {
        $this->postJson('/api/entidades', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['status', 'detail', 'errors' => ['nome', 'nif', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao']]);
    });

    it('devolve 422 quando nif é vazio', function (): void {
        $this->postJson('/api/entidades', [
            'nome' => 'Empresa Teste',
            'nif' => '',
            'e_cliente' => true,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nif']);
    });

    it('devolve 422 quando nif excede 20 caracteres', function (): void {
        $this->postJson('/api/entidades', [
            'nome' => 'Empresa Teste',
            'nif' => str_repeat('1', 21),
            'e_cliente' => true,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nif']);
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();
    Activity::query()->delete();

    $this->postJson('/api/entidades', [
        'nome' => 'Empresa Utilizador',
        'nif' => '999999999',
        'e_cliente' => true,
        'e_fornecedor' => false,
        'e_empresa_aplicacao' => false,
    ])->assertForbidden();

    expect(Activity::count())->toBe(0);
});

it('guest sem token recebe 401', function (): void {
    $this->postJson('/api/entidades', [
        'nome' => 'Empresa Guest',
        'nif' => '999999999',
        'e_cliente' => false,
        'e_fornecedor' => true,
        'e_empresa_aplicacao' => false,
    ])->assertUnauthorized();
});
