<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Os seeds de roles deixam actividade persistente fora da transação do teste.
beforeEach(fn () => Activity::query()->delete());

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('elimina definitivamente sem documentos e devolve 204', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        Activity::query()->delete();

        $this->deleteJson("/api/categorias-documento/{$categoria->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categorias_documento', ['id' => $categoria->id]);
    });

    it('faz soft delete com documentos e devolve 204', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        Documento::factory()->create(['id_categoria' => $categoria->id]);
        Activity::query()->delete();

        $this->deleteJson("/api/categorias-documento/{$categoria->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('categorias_documento', ['id' => $categoria->id]);
    });

    it('devolve 404 quando a categoria não existe', function (): void {
        $this->deleteJson('/api/categorias-documento/00000000-0000-0000-0000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
            ->assertJsonPath('detail', 'Recurso não encontrado.');
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    criarEAutenticarUtilizador();
    Activity::query()->delete();

    $this->deleteJson("/api/categorias-documento/{$categoria->id}")
        ->assertForbidden();

    expect(Activity::count())->toBe(0);
});

it('guest sem token recebe 401', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $this->deleteJson("/api/categorias-documento/{$categoria->id}")
        ->assertUnauthorized();
});
