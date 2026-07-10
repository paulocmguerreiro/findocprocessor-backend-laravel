<?php

declare(strict_types=1);

use App\Features\TipoDocumento\Ver\VerTipoDocumentoAction;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['tipos_documento'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve o modelo quando recebe TipoDocumento directamente', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        $resultado = app(VerTipoDocumentoAction::class)->handle($tipoDocumento);

        expect($resultado->id)->toBe($tipoDocumento->id)
            ->and($resultado->relationLoaded('categoria'))->toBeTrue();
    });

    it('resolve o modelo quando recebe string UUID', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        $resultado = app(VerTipoDocumentoAction::class)->handle($tipoDocumento->id);

        expect($resultado->id)->toBe($tipoDocumento->id);
    });

    it('lança ModelNotFoundException quando o UUID não existe', function (): void {
        expect(fn (): TipoDocumento => app(VerTipoDocumentoAction::class)->handle('00000000-0000-0000-0000-000000000000'))
            ->toThrow(ModelNotFoundException::class);
    });

    it('cacheia o registo após primeira chamada', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        app(VerTipoDocumentoAction::class)->handle($tipoDocumento);

        $chave = app(CacheServico::class)->criarChave(
            TagCache::TiposDocumento,
            TagOperacao::Ver,
            ['id' => $tipoDocumento->id],
        );

        expect(Cache::tags(['tipos_documento'])->has($chave))->toBeTrue();
    });
});

describe('sem permissão de leitura', function (): void {
    it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();
        $this->actingAs(User::factory()->create()); // sem role — sem tipos-documento.ver

        expect(fn (): TipoDocumento => app(VerTipoDocumentoAction::class)->handle($tipoDocumento))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $tipoDocumento = TipoDocumento::factory()->create();

    expect(fn (): TipoDocumento => app(VerTipoDocumentoAction::class)->handle($tipoDocumento))
        ->toThrow(AuthorizationException::class);
});
