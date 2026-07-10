<?php

declare(strict_types=1);

use App\Models\TipoDocumento;
use App\Policies\TipoDocumentoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new TipoDocumentoPolicy;
    $this->tipoDocumento = TipoDocumento::factory()->make();
});

describe('admin tem todas as permissões', function (): void {
    beforeEach(function (): void {
        $this->utilizador = criarAdmin();
    });

    it('permite viewAny', function (): void {
        expect($this->policy->viewAny($this->utilizador))->toBeTrue();
    });

    it('permite view', function (): void {
        expect($this->policy->view($this->utilizador, $this->tipoDocumento))->toBeTrue();
    });

    it('permite create', function (): void {
        expect($this->policy->create($this->utilizador))->toBeTrue();
    });

    it('permite update', function (): void {
        expect($this->policy->update($this->utilizador, $this->tipoDocumento))->toBeTrue();
    });

    it('permite delete', function (): void {
        expect($this->policy->delete($this->utilizador, $this->tipoDocumento))->toBeTrue();
    });
});

describe('utilizador só tem leitura', function (): void {
    beforeEach(function (): void {
        $this->utilizador = criarUtilizador();
    });

    it('permite viewAny', function (): void {
        expect($this->policy->viewAny($this->utilizador))->toBeTrue();
    });

    it('permite view', function (): void {
        expect($this->policy->view($this->utilizador, $this->tipoDocumento))->toBeTrue();
    });

    it('nega create', function (): void {
        expect($this->policy->create($this->utilizador))->toBeFalse();
    });

    it('nega update', function (): void {
        expect($this->policy->update($this->utilizador, $this->tipoDocumento))->toBeFalse();
    });

    it('nega delete', function (): void {
        expect($this->policy->delete($this->utilizador, $this->tipoDocumento))->toBeFalse();
    });
});
