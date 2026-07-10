<?php

declare(strict_types=1);

use App\Features\TipoDocumento\Eliminar\EliminarTipoDocumentoAction;
use App\Models\TipoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['tipos_documento'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('elimina definitivamente quando recebe TipoDocumento directamente', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        app(EliminarTipoDocumentoAction::class)->handle($tipoDocumento);

        $this->assertDatabaseMissing('tipos_documento', ['id' => $tipoDocumento->id]);
    });

    it('elimina definitivamente quando recebe string UUID', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        app(EliminarTipoDocumentoAction::class)->handle($tipoDocumento->id);

        $this->assertDatabaseMissing('tipos_documento', ['id' => $tipoDocumento->id]);
    });

    it('lança ModelNotFoundException quando o UUID não existe', function (): void {
        expect(fn () => app(EliminarTipoDocumentoAction::class)->handle('00000000-0000-0000-0000-000000000000'))
            ->toThrow(ModelNotFoundException::class);
    });

    it('faz rollback quando ocorre excepção durante eliminação', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        TipoDocumento::deleting(function (): void {
            throw new RuntimeException('falha simulada durante eliminação');
        });

        expect(fn () => app(EliminarTipoDocumentoAction::class)->handle($tipoDocumento))
            ->toThrow(RuntimeException::class, 'falha simulada durante eliminação');

        $this->assertDatabaseHas('tipos_documento', ['id' => $tipoDocumento->id]);
    });
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        expect(fn () => app(EliminarTipoDocumentoAction::class)->handle($tipoDocumento))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $tipoDocumento = TipoDocumento::factory()->create();

    expect(fn () => app(EliminarTipoDocumentoAction::class)->handle($tipoDocumento))
        ->toThrow(AuthorizationException::class);
});
