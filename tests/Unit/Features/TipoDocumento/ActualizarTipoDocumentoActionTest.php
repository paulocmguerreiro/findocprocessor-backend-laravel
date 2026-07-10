<?php

declare(strict_types=1);

use App\Features\TipoDocumento\Actualizar\ActualizarTipoDocumentoAction;
use App\Features\TipoDocumento\Actualizar\ActualizarTipoDocumentoDto;
use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['tipos_documento'])->flush());

function dtoActualizarTipoDocumento(CategoriaDocumento $categoria, array $sobrepor = []): ActualizarTipoDocumentoDto
{
    $dados = array_merge([
        'nome' => 'Nome Actualizado',
        'descricao' => 'Descrição Actualizada',
        'idCategoria' => $categoria->id,
        'posicaoEmpresaMae' => PosicaoEmpresaMae::Cliente,
        'esperaDataDocumento' => true,
        'esperaFornecedor' => true,
        'esperaCliente' => false,
        'esperaValor' => true,
    ], $sobrepor);

    return new ActualizarTipoDocumentoDto(...$dados);
}

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('actualiza quando recebe TipoDocumento directamente', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create(['nome' => 'Original']);

        $resultado = app(ActualizarTipoDocumentoAction::class)->handle($tipoDocumento, dtoActualizarTipoDocumento($categoria));

        expect($resultado->nome)->toBe('Nome Actualizado')
            ->and($resultado->relationLoaded('categoria'))->toBeTrue();
    });

    it('actualiza quando recebe string UUID', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create(['nome' => 'Original']);

        $resultado = app(ActualizarTipoDocumentoAction::class)->handle($tipoDocumento->id, dtoActualizarTipoDocumento($categoria));

        expect($resultado->nome)->toBe('Nome Actualizado');
    });

    it('lança ModelNotFoundException quando o UUID não existe', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        expect(fn (): TipoDocumento => app(ActualizarTipoDocumentoAction::class)->handle('00000000-0000-0000-0000-000000000000', dtoActualizarTipoDocumento($categoria)))
            ->toThrow(ModelNotFoundException::class);
    });

    it('faz rollback quando ocorre excepção durante update', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create(['nome' => 'Original']);

        TipoDocumento::saved(function (): void {
            throw new RuntimeException('falha simulada durante update');
        });

        expect(fn (): TipoDocumento => app(ActualizarTipoDocumentoAction::class)->handle($tipoDocumento, dtoActualizarTipoDocumento($categoria)))
            ->toThrow(RuntimeException::class, 'falha simulada durante update');

        $this->assertDatabaseHas('tipos_documento', ['id' => $tipoDocumento->id, 'nome' => 'Original']);
    });
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create();

        expect(fn (): TipoDocumento => app(ActualizarTipoDocumentoAction::class)->handle($tipoDocumento, dtoActualizarTipoDocumento($categoria)))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $categoria = CategoriaDocumento::factory()->create();
    $tipoDocumento = TipoDocumento::factory()->for($categoria, 'categoria')->create();

    expect(fn (): TipoDocumento => app(ActualizarTipoDocumentoAction::class)->handle($tipoDocumento, dtoActualizarTipoDocumento($categoria)))
        ->toThrow(AuthorizationException::class);
});
