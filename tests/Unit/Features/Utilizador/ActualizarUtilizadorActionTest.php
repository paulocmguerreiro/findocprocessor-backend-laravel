<?php

declare(strict_types=1);

use App\Features\Utilizador\Actualizar\ActualizarUtilizadorAction;
use App\Features\Utilizador\Actualizar\ActualizarUtilizadorDto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('actualiza nome e email', function (): void {
        $alvo = User::factory()->create(['name' => 'Antigo']);

        $dto = new ActualizarUtilizadorDto(nome: 'Novo', email: 'novo@example.com', password: null);
        $resultado = app(ActualizarUtilizadorAction::class)->handle($alvo, $dto);

        expect($resultado->name)->toBe('Novo')
            ->and($resultado->email)->toBe('novo@example.com');
    });

    it('com password null não altera a password existente', function (): void {
        $alvo = User::factory()->create();
        $hashOriginal = $alvo->password;

        $dto = new ActualizarUtilizadorDto(nome: 'X', email: $alvo->email, password: null);
        app(ActualizarUtilizadorAction::class)->handle($alvo, $dto);

        expect($alvo->fresh()->password)->toBe($hashOriginal);
    });

    it('com password actualiza-a de forma protegida', function (): void {
        $alvo = User::factory()->create();

        $dto = new ActualizarUtilizadorDto(nome: 'X', email: $alvo->email, password: 'NovaPass1!');
        app(ActualizarUtilizadorAction::class)->handle($alvo, $dto);

        expect(Hash::check('NovaPass1!', $alvo->fresh()->password))->toBeTrue();
    });
});

it('sem permissão lança AuthorizationException', function (): void {
    $this->actingAs(criarUtilizador());
    $alvo = User::factory()->create();

    $dto = new ActualizarUtilizadorDto(nome: 'X', email: 'x@example.com', password: null);

    expect(fn (): User => app(ActualizarUtilizadorAction::class)->handle($alvo, $dto))
        ->toThrow(AuthorizationException::class);
});
