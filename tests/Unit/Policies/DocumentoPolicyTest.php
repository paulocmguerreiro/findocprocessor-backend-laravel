<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Models\User;
use App\Policies\DocumentoPolicy;

beforeEach(function (): void {
    $this->policy = new DocumentoPolicy;
    $this->utilizador = User::factory()->make();
    $this->documento = Documento::factory()->pendente()->make();
});

it('viewAny devolve true', function (): void {
    expect($this->policy->viewAny($this->utilizador))->toBeTrue();
});

it('view devolve true', function (): void {
    expect($this->policy->view($this->utilizador, $this->documento))->toBeTrue();
});

it('create devolve true', function (): void {
    expect($this->policy->create($this->utilizador))->toBeTrue();
});

it('update devolve true', function (): void {
    expect($this->policy->update($this->utilizador, $this->documento))->toBeTrue();
});

it('delete devolve true', function (): void {
    expect($this->policy->delete($this->utilizador, $this->documento))->toBeTrue();
});
