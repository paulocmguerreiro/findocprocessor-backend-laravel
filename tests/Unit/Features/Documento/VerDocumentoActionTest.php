<?php

declare(strict_types=1);

use App\Features\Documento\Ver\VerDocumentoAction;
use App\Models\Documento;
use App\Models\EtapaDocumento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['documentos'])->flush());

describe('autenticado', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve o documento com o histórico carregado', function (): void {
        $documento = Documento::factory()->processado()->create();
        EtapaDocumento::factory()->processado()->for($documento, 'documento')->create();

        $resultado = app(VerDocumentoAction::class)->handle($documento);

        expect($resultado->is($documento))->toBeTrue()
            ->and($resultado->relationLoaded('historico'))->toBeTrue()
            ->and($resultado->historico)->toHaveCount(1);
    });

    it('resolve o documento a partir do id em string', function (): void {
        $documento = Documento::factory()->pendente()->create();

        $resultado = app(VerDocumentoAction::class)->handle($documento->id);

        expect($resultado->is($documento))->toBeTrue();
    });

    it('lança ModelNotFoundException quando o id não existe', function (): void {
        expect(fn (): Documento => app(VerDocumentoAction::class)->handle('id-inexistente'))
            ->toThrow(ModelNotFoundException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    $documento = Documento::factory()->processado()->create();

    expect(fn (): Documento => app(VerDocumentoAction::class)->handle($documento))
        ->toThrow(AuthorizationException::class);
});

it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
    $documento = Documento::factory()->processado()->create();
    $this->actingAs(User::factory()->create()); // sem role — sem documentos.ver

    expect(fn (): Documento => app(VerDocumentoAction::class)->handle($documento))
        ->toThrow(AuthorizationException::class);
});
