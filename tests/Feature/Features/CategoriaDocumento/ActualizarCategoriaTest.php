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

function payloadActualizar(array $sobrepor = []): array
{
    return array_merge([
        'nome' => 'Nome Actualizado',
        'slug' => 'nome-actualizado',
        'tipo_movimento' => TipoMovimento::Neutro->value,
    ], $sobrepor);
}

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('actualiza todos os campos e devolve 200 com o recurso', function (): void {
        $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->create(['nome' => 'Nome Original']);
        Activity::query()->delete();

        $this->putJson("/api/categorias-documento/{$categoria->id}", payloadActualizar([
            'nome' => 'Nome Actualizado',
            'slug' => 'nome-actualizado',
            'tipo_movimento' => TipoMovimento::Credito->value,
        ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', fn (AssertableJson $data): AssertableJson => $data
                    ->where('id', $categoria->id)
                    ->where('nome', 'Nome Actualizado')
                    ->where('slug', 'nome-actualizado')
                    ->where('tipo_movimento', TipoMovimento::Credito->value)
                    ->where('deleted_at', null)
                )
            );

        $this->assertDatabaseHas('categorias_documento', [
            'id' => $categoria->id,
            'nome' => 'Nome Actualizado',
            'slug' => 'nome-actualizado',
            'tipo_movimento' => TipoMovimento::Credito->value,
        ]);

        expect(Activity::count())->toBe(1)
            ->and(Activity::query()->first()->event)->toBe('updated');
    });

    it('devolve 404 quando a categoria não existe', function (): void {
        $this->putJson('/api/categorias-documento/00000000-0000-0000-0000-000000000000', payloadActualizar())
            ->assertNotFound()
            ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
            ->assertJsonPath('detail', 'Recurso não encontrado.');
    });

    it('devolve 422 quando campo obrigatório está ausente', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $this->putJson("/api/categorias-documento/{$categoria->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath('status', Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['status', 'detail', 'errors' => ['nome', 'slug', 'tipo_movimento']]);
    });

    it('devolve 422 quando o slug já pertence a outra categoria', function (): void {
        CategoriaDocumento::factory()->create(['slug' => 'slug-existente']);
        $categoria = CategoriaDocumento::factory()->create(['slug' => 'outro-slug']);

        $this->putJson("/api/categorias-documento/{$categoria->id}", payloadActualizar(['slug' => 'slug-existente']))
            ->assertUnprocessable()
            ->assertJsonPath('status', Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['status', 'detail', 'errors' => ['slug']]);
    });

    it('permite actualizar o slug da própria categoria sem erro de unicidade', function (): void {
        $categoria = CategoriaDocumento::factory()->create(['slug' => 'slug-proprio']);

        $this->putJson("/api/categorias-documento/{$categoria->id}", payloadActualizar(['slug' => 'slug-proprio']))
            ->assertOk();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    criarEAutenticarUtilizador();
    Activity::query()->delete();

    $this->putJson("/api/categorias-documento/{$categoria->id}", payloadActualizar())
        ->assertForbidden();

    expect(Activity::count())->toBe(0);
});

it('guest sem token recebe 401', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $this->putJson("/api/categorias-documento/{$categoria->id}", payloadActualizar())
        ->assertUnauthorized();
});
