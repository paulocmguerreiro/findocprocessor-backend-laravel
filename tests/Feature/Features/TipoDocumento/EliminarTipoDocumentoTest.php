<?php

declare(strict_types=1);

use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Activity::query()->delete());

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('elimina definitivamente e devolve 204', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();
        Activity::query()->delete();

        $this->deleteJson("/api/tipos-documento/{$tipoDocumento->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tipos_documento', ['id' => $tipoDocumento->id]);
    });

    it('devolve 404 quando o tipo de documento não existe', function (): void {
        $this->deleteJson('/api/tipos-documento/00000000-0000-0000-0000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
            ->assertJsonPath('detail', 'Recurso não encontrado.');
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $tipoDocumento = TipoDocumento::factory()->create();
    criarEAutenticarUtilizador();
    Activity::query()->delete();

    $this->deleteJson("/api/tipos-documento/{$tipoDocumento->id}")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $tipoDocumento = TipoDocumento::factory()->create();

    $this->deleteJson("/api/tipos-documento/{$tipoDocumento->id}")
        ->assertUnauthorized();
});
