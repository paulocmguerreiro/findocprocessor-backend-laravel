<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Route::get('/test-validation', function (): never {
        throw ValidationException::withMessages(['nome' => ['O campo nome é obrigatório.']]);
    });

    Route::get('/test-not-found', function (): never {
        throw new ModelNotFoundException();
    });

    Route::get('/test-forbidden', function (): never {
        throw new AuthorizationException();
    });

    Route::get('/test-unauthenticated', function (): never {
        throw new AuthenticationException();
    });

    Route::get('/test-server-error', function (): never {
        throw new RuntimeException('mensagem interna secreta');
    });
});

it('ValidationException mapeia para 422 com campo errors', function (): void {
    $this->getJson('/test-validation')
        ->assertUnprocessable()
        ->assertJsonPath('status', 422)
        ->assertJsonStructure(['status', 'detail', 'errors'])
        ->assertJsonPath('errors.nome.0', 'O campo nome é obrigatório.');
});

it('ModelNotFoundException mapeia para 404', function (): void {
    $this->getJson('/test-not-found')
        ->assertNotFound()
        ->assertJsonPath('status', 404)
        ->assertJsonPath('detail', 'Recurso não encontrado.');
});

it('AuthorizationException mapeia para 403', function (): void {
    $this->getJson('/test-forbidden')
        ->assertForbidden()
        ->assertJsonPath('status', 403)
        ->assertJsonPath('detail', 'Sem permissão para aceder a este recurso.');
});

it('AuthenticationException mapeia para 401', function (): void {
    $this->getJson('/test-unauthenticated')
        ->assertUnauthorized()
        ->assertJsonPath('status', 401)
        ->assertJsonPath('detail', 'Não autenticado.');
});

it('Throwable genérico mapeia para 500 sem expor detalhes internos', function (): void {
    $this->getJson('/test-server-error')
        ->assertStatus(500)
        ->assertJsonPath('status', 500)
        ->assertJsonMissingPath('trace')
        ->assertJsonMissingPath('message');
});
