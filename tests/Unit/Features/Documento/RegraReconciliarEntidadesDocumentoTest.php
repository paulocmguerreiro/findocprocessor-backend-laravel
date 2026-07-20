<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\ConcluirExtracao\RegraReconciliarEntidadesDocumento;
use App\Features\Documento\Processamento\ConcluirExtracao\ResultadoReconciliacaoEntidades;
use App\Infrastructure\AI\ResultadoExtracaoIA;
use App\Models\Entidade;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $sobrepor
 */
function resultadoCompleto(TipoDocumento $tipo, array $sobrepor = []): ResultadoExtracaoIA
{
    return ResultadoExtracaoIA::completo(
        tipoDocumento: $tipo,
        idCategoria: $tipo->id_categoria,
        dataDocumento: Carbon::parse('2026-06-25'),
        nifFornecedor: array_key_exists('nifFornecedor', $sobrepor) ? $sobrepor['nifFornecedor'] : '509111111',
        nomeFornecedor: array_key_exists('nomeFornecedor', $sobrepor) ? $sobrepor['nomeFornecedor'] : 'Fornecedor Externo Lda',
        nifCliente: array_key_exists('nifCliente', $sobrepor) ? $sobrepor['nifCliente'] : '509222222',
        nomeCliente: array_key_exists('nomeCliente', $sobrepor) ? $sobrepor['nomeCliente'] : 'Cliente Externo Lda',
        valor: 100.0,
    );
}

function reconciliar(ResultadoExtracaoIA $resultado, TipoDocumento $tipo): ResultadoReconciliacaoEntidades
{
    return app(RegraReconciliarEntidadesDocumento::class)->handle($resultado, $tipo);
}

it('resolve empresa mãe no lado cliente e find-or-create no lado fornecedor (compra)', function (): void {
    $empresaMae = Entidade::factory()->empresaAplicacao()->create(['nome' => 'Minha Empresa SA']);
    $tipo = TipoDocumento::factory()->create([
        'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente,
        'espera_fornecedor' => true,
    ]);

    $reconciliado = reconciliar(resultadoCompleto($tipo, ['nifFornecedor' => '509999999', 'nomeFornecedor' => 'ACME Lda']), $tipo);

    expect($reconciliado->idCliente)->toBe($empresaMae->id)
        ->and($reconciliado->idFornecedor)->not->toBeNull()
        ->and($reconciliado->idCategoria)->toBe($tipo->id_categoria);

    $this->assertDatabaseHas('entidades', ['nif' => '509999999', 'nome' => 'ACME Lda', 'e_fornecedor' => true]);
});

it('resolve empresa mãe no lado fornecedor e find-or-create no lado cliente (venda)', function (): void {
    $empresaMae = Entidade::factory()->empresaAplicacao()->create();
    $tipo = TipoDocumento::factory()->create([
        'posicao_empresa_mae' => PosicaoEmpresaMae::Fornecedor,
        'espera_cliente' => true,
    ]);

    $reconciliado = reconciliar(resultadoCompleto($tipo, ['nifCliente' => '501234567', 'nomeCliente' => 'Cliente Final Lda']), $tipo);

    expect($reconciliado->idFornecedor)->toBe($empresaMae->id)
        ->and($reconciliado->idCliente)->not->toBeNull();

    $this->assertDatabaseHas('entidades', ['nif' => '501234567', 'e_cliente' => true]);
});

it('reutiliza a entidade existente por NIF em vez de duplicar (não actualiza o nome)', function (): void {
    Entidade::factory()->empresaAplicacao()->create();
    $existente = Entidade::factory()->fornecedor()->create(['nif' => '509999999', 'nome' => 'ACME Original']);
    $tipo = TipoDocumento::factory()->create(['posicao_empresa_mae' => PosicaoEmpresaMae::Cliente, 'espera_fornecedor' => true]);

    $reconciliado = reconciliar(resultadoCompleto($tipo, ['nifFornecedor' => '509999999', 'nomeFornecedor' => 'ACME Novo Nome']), $tipo);

    expect($reconciliado->idFornecedor)->toBe($existente->id)
        ->and(Entidade::query()->where('nif', '509999999')->count())->toBe(1);

    $this->assertDatabaseHas('entidades', ['nif' => '509999999', 'nome' => 'ACME Original']);
});

it('deixa o lado fornecedor a null quando não é esperado (extrato) sem criar entidade', function (): void {
    $empresaMae = Entidade::factory()->empresaAplicacao()->create(['nome' => 'Minha Empresa SA']);
    $tipo = TipoDocumento::factory()->create([
        'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente,
        'espera_fornecedor' => false,
    ]);

    $reconciliado = reconciliar(resultadoCompleto($tipo, ['nomeFornecedor' => 'Banco XYZ', 'nifFornecedor' => null]), $tipo);

    expect($reconciliado->idFornecedor)->toBeNull()
        ->and($reconciliado->idCliente)->toBe($empresaMae->id)
        ->and($reconciliado->nomeFornecedorParaNome)->toBe('Banco XYZ')
        ->and(Entidade::query()->where('nome', 'Banco XYZ')->exists())->toBeFalse();
});

it('faz fallback ao nome da empresa mãe no naming quando o fornecedor extraído vem vazio', function (): void {
    Entidade::factory()->empresaAplicacao()->create(['nome' => 'Minha Empresa SA']);
    $tipo = TipoDocumento::factory()->create(['posicao_empresa_mae' => PosicaoEmpresaMae::Cliente, 'espera_fornecedor' => false]);

    $reconciliado = reconciliar(resultadoCompleto($tipo, ['nomeFornecedor' => null, 'nifFornecedor' => null]), $tipo);

    expect($reconciliado->nomeFornecedorParaNome)->toBe('Minha Empresa SA');
});

it('lança ModelNotFoundException quando não há empresa mãe configurada (RN-06)', function (): void {
    $tipo = TipoDocumento::factory()->create(['posicao_empresa_mae' => PosicaoEmpresaMae::Cliente]);

    expect(fn (): mixed => reconciliar(resultadoCompleto($tipo), $tipo))
        ->toThrow(ModelNotFoundException::class);
});
