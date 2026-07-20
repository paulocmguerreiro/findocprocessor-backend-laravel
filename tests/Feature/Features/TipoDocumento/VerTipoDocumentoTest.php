<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('devolve tipo de documento existente com estrutura correcta', function (): void {
        $categoria = CategoriaDocumento::factory()->create(['nome' => 'Categoria Ver']);
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create();

        $this->getJson("/api/tipos-documento/{$tipoDocumento->id}")
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', fn (AssertableJson $data): AssertableJson => $data
                    ->where('id', $tipoDocumento->id)
                    ->where('nome', $tipoDocumento->nome)
                    ->has('categoria', fn (AssertableJson $categoriaJson): AssertableJson => $categoriaJson
                        ->where('id', $categoria->id)
                        ->where('nome', 'Categoria Ver')
                        ->etc()
                    )
                    ->etc()
                )
            );
    });

    it('devolve 404 quando o tipo de documento não existe', function (): void {
        $this->getJson('/api/tipos-documento/00000000-0000-0000-0000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
            ->assertJsonPath('detail', 'Recurso não encontrado.');
    });
});

it('utilizador com permissão de leitura devolve 200', function (): void {
    $tipoDocumento = TipoDocumento::factory()->create();
    criarEAutenticarUtilizador();

    $this->getJson("/api/tipos-documento/{$tipoDocumento->id}")
        ->assertOk();
});

it('utilizador sem permissão de leitura recebe 403', function (): void {
    $tipoDocumento = TipoDocumento::factory()->create();
    criarEAutenticarSemRole();

    $this->getJson("/api/tipos-documento/{$tipoDocumento->id}")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $tipoDocumento = TipoDocumento::factory()->create();

    $this->getJson("/api/tipos-documento/{$tipoDocumento->id}")
        ->assertUnauthorized();
});
