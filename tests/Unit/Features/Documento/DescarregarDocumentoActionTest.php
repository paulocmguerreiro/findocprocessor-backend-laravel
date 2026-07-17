<?php

declare(strict_types=1);

use App\Features\Documento\Pesquisa\Descarregar\DescarregarDocumentoAction;
use App\Features\Documento\Pesquisa\Descarregar\FicheiroDocumentoDto;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    $this->actingAs(criarAdmin());
});

it('devolve a referência do ficheiro quando existe no disco', function (): void {
    $documento = Documento::factory()->processado()->create();
    Storage::disk('processado')->put($documento->nome_ficheiro_storage, 'conteudo');

    $referencia = app(DescarregarDocumentoAction::class)->handle($documento);

    expect($referencia)->toBeInstanceOf(FicheiroDocumentoDto::class)
        ->and($referencia->disco)->toBe($documento->disco_storage)
        ->and($referencia->nomeStorage)->toBe($documento->nome_ficheiro_storage)
        ->and($referencia->nomeOriginal)->toBe($documento->nome_ficheiro_original);
});

it('lança 404 quando o ficheiro não existe no disco', function (): void {
    $documento = Documento::factory()->processado()->create();

    expect(fn (): FicheiroDocumentoDto => app(DescarregarDocumentoAction::class)->handle($documento))
        ->toThrow(NotFoundHttpException::class);
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    $documento = Documento::factory()->processado()->create();
    auth()->logout();

    expect(fn (): FicheiroDocumentoDto => app(DescarregarDocumentoAction::class)->handle($documento))
        ->toThrow(AuthorizationException::class);
});

it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
    $documento = Documento::factory()->processado()->create();
    $this->actingAs(User::factory()->create()); // sem role — sem documentos.ver

    expect(fn (): FicheiroDocumentoDto => app(DescarregarDocumentoAction::class)->handle($documento))
        ->toThrow(AuthorizationException::class);
});
