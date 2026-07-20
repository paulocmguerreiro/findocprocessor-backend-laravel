<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Activity::query()->delete());

function payloadActualizarTipoDocumento(string $idCategoria, array $sobrepor = []): array
{
    return array_merge([
        'nome' => 'Nome Actualizado',
        'descricao' => 'Descrição Actualizada',
        'id_categoria' => $idCategoria,
        'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente->value,
        'espera_data_documento' => true,
        'espera_fornecedor' => true,
        'espera_cliente' => false,
        'espera_valor' => true,
    ], $sobrepor);
}

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('actualiza todos os campos e devolve 200 com o recurso', function (): void {
        $categoria = CategoriaDocumento::factory()->create(['nome' => 'Categoria Actualizada']);
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create(['nome' => 'Nome Original']);
        Activity::query()->delete();

        $this->putJson("/api/tipos-documento/{$tipoDocumento->id}", payloadActualizarTipoDocumento($categoria->id))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', fn (AssertableJson $data): AssertableJson => $data
                    ->where('id', $tipoDocumento->id)
                    ->where('nome', 'Nome Actualizado')
                    ->has('categoria', fn (AssertableJson $categoriaJson): AssertableJson => $categoriaJson
                        ->where('id', $categoria->id)
                        ->where('nome', 'Categoria Actualizada')
                        ->etc()
                    )
                    ->etc()
                )
            );

        $this->assertDatabaseHas('tipos_documento', ['id' => $tipoDocumento->id, 'nome' => 'Nome Actualizado']);
    });

    it('devolve 404 quando o tipo de documento não existe', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $this->putJson('/api/tipos-documento/00000000-0000-0000-0000-000000000000', payloadActualizarTipoDocumento($categoria->id))
            ->assertNotFound()
            ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
            ->assertJsonPath('detail', 'Recurso não encontrado.');
    });

    it('devolve 422 quando campo obrigatório está ausente', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create();

        $this->putJson("/api/tipos-documento/{$tipoDocumento->id}", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['status', 'detail', 'errors' => ['nome', 'descricao', 'id_categoria', 'posicao_empresa_mae', 'espera_data_documento', 'espera_fornecedor', 'espera_cliente', 'espera_valor']]);
    });

    it('devolve 422 quando o nome já pertence a outro tipo de documento', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        TipoDocumento::factory()->for($categoria, 'categoria')->create(['nome' => 'Nome Existente']);
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create(['nome' => 'Outro Nome']);

        $this->putJson("/api/tipos-documento/{$tipoDocumento->id}", payloadActualizarTipoDocumento($categoria->id, ['nome' => 'Nome Existente']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome']);
    });

    it('permite actualizar o próprio tipo de documento sem erro de unicidade de nome', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create(['nome' => 'Nome Próprio']);

        $this->putJson("/api/tipos-documento/{$tipoDocumento->id}", payloadActualizarTipoDocumento($categoria->id, ['nome' => 'Nome Próprio']))
            ->assertOk();
    });

    it('devolve 422 quando os 4 espera_* são todos false', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create();

        $this->putJson("/api/tipos-documento/{$tipoDocumento->id}", payloadActualizarTipoDocumento($categoria->id, [
            'espera_data_documento' => false,
            'espera_fornecedor' => false,
            'espera_cliente' => false,
            'espera_valor' => false,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['espera_data_documento']);
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create();
    criarEAutenticarUtilizador();
    Activity::query()->delete();

    $this->putJson("/api/tipos-documento/{$tipoDocumento->id}", payloadActualizarTipoDocumento($categoria->id))
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create();

    $this->putJson("/api/tipos-documento/{$tipoDocumento->id}", payloadActualizarTipoDocumento($categoria->id))
        ->assertUnauthorized();
});
