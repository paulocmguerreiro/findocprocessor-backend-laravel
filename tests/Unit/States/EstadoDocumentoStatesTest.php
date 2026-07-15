<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\States\DocumentoAguardaEnvio;
use App\Shared\States\DocumentoAguardaResposta;
use App\Shared\States\DocumentoEnviado;
use App\Shared\States\DocumentoErro;
use App\Shared\States\DocumentoPendente;
use App\Shared\States\DocumentoPerigoso;
use App\Shared\States\DocumentoProcessado;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('estado() devolve o state object correcto', function (): void {
    it('mapeia cada estado para a sua classe', function (string $state, string $classe, EstadoDocumento $estado): void {
        $documento = Documento::factory()->{$state}()->create();

        $stateObject = $documento->estado();

        expect($stateObject)->toBeInstanceOf($classe)
            ->and($stateObject->obterEstado())->toBe($estado);
    })->with([
        'pendente' => ['pendente', DocumentoPendente::class, EstadoDocumento::Pendente],
        'aguardaEnvio' => ['aguardaEnvio', DocumentoAguardaEnvio::class, EstadoDocumento::AguardaEnvio],
        'enviado' => ['enviado', DocumentoEnviado::class, EstadoDocumento::Enviado],
        'aguardaResposta' => ['aguardaResposta', DocumentoAguardaResposta::class, EstadoDocumento::AguardaResposta],
        'processado' => ['processado', DocumentoProcessado::class, EstadoDocumento::Processado],
        'erro' => ['erro', DocumentoErro::class, EstadoDocumento::Erro],
        'perigoso' => ['perigoso', DocumentoPerigoso::class, EstadoDocumento::Perigoso],
    ]);
});

describe('getters comuns', function (): void {
    it('expõem id, discoStorage e nomeFicheiroStorage do documento', function (string $state): void {
        $documento = Documento::factory()->{$state}()->create();

        $stateObject = $documento->estado();

        expect($stateObject->obterId())->toBe($documento->id)
            ->and($stateObject->obterDiscoStorage())->toBe($documento->disco_storage)
            ->and($stateObject->obterNomeFicheiroStorage())->toBe($documento->nome_ficheiro_storage);
    })->with(['pendente', 'aguardaEnvio', 'enviado', 'aguardaResposta', 'processado', 'erro', 'perigoso']);
});

describe('getters dos estados parciais', function (): void {
    it('expõem nomeFicheiroOriginal e hashSha256', function (string $state): void {
        $documento = Documento::factory()->{$state}()->create();

        /** @var DocumentoPendente $stateObject */
        $stateObject = $documento->estado();

        expect($stateObject->obterNomeFicheiroOriginal())->toBe($documento->nome_ficheiro_original)
            ->and($stateObject->obterHashSha256())->toBe($documento->hash_sha256);
    })->with(['pendente', 'aguardaEnvio', 'enviado', 'aguardaResposta']);
});

describe('DocumentoProcessado — estado completo', function (): void {
    it('expõe todos os campos de domínio', function (): void {
        $documento = Documento::factory()->processado()->create();

        /** @var DocumentoProcessado $stateObject */
        $stateObject = $documento->estado();

        expect($stateObject->obterNomeFicheiroOriginal())->toBe($documento->nome_ficheiro_original)
            ->and($stateObject->obterHashSha256())->toBe($documento->hash_sha256)
            ->and($stateObject->obterIdFornecedor())->toBe($documento->id_fornecedor)
            ->and($stateObject->obterIdCliente())->toBe($documento->id_cliente)
            ->and($stateObject->obterIdCategoria())->toBe($documento->id_categoria)
            ->and($stateObject->obterValor())->toBe($documento->valor)
            ->and($stateObject->obterDataDocumento())->toBeInstanceOf(DateTimeInterface::class);
    });
});

describe('imutabilidade', function (): void {
    it('cada state object é final readonly', function (string $classe): void {
        $reflexao = new ReflectionClass($classe);

        expect($reflexao->isFinal())->toBeTrue()
            ->and($reflexao->isReadOnly())->toBeTrue();
    })->with([
        DocumentoPendente::class,
        DocumentoAguardaEnvio::class,
        DocumentoEnviado::class,
        DocumentoAguardaResposta::class,
        DocumentoProcessado::class,
        DocumentoErro::class,
        DocumentoPerigoso::class,
    ]);
});
