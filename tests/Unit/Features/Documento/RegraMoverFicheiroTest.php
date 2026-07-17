<?php

declare(strict_types=1);

use App\Features\Documento\Operacoes\Transicao\RegraMoverFicheiro;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

it('move o ficheiro entre discos distintos e apaga a origem', function (): void {
    Storage::fake('entrada');
    Storage::fake('enviado');
    Storage::disk('entrada')->put('doc.pdf', 'conteudo');

    $resultado = (new RegraMoverFicheiro)->handle('entrada', 'doc.pdf', EstadoDocumento::AnaliseIaLocal);

    expect($resultado)->toBe(['disco' => 'enviado', 'nome' => 'doc.pdf']);
    Storage::disk('enviado')->assertExists('doc.pdf');
    Storage::disk('entrada')->assertMissing('doc.pdf');
});

it('não move nada quando disco e nome não mudam', function (): void {
    Storage::fake('entrada');
    Storage::disk('entrada')->put('doc.pdf', 'conteudo');

    $resultado = (new RegraMoverFicheiro)->handle('entrada', 'doc.pdf', EstadoDocumento::AnaliseMalware);

    expect($resultado)->toBe(['disco' => 'entrada', 'nome' => 'doc.pdf']);
    Storage::disk('entrada')->assertExists('doc.pdf');
});

it('renomeia no mesmo disco quando só o nome muda', function (): void {
    Storage::fake('processado');
    Storage::disk('processado')->put('antigo.pdf', 'conteudo');

    $resultado = (new RegraMoverFicheiro)->handle('processado', 'antigo.pdf', EstadoDocumento::Processado, 'novo.pdf');

    expect($resultado)->toBe(['disco' => 'processado', 'nome' => 'novo.pdf']);
    Storage::disk('processado')->assertExists('novo.pdf');
    Storage::disk('processado')->assertMissing('antigo.pdf');
});

it('mapeia cada um dos 9 estados para o disco correcto', function (EstadoDocumento $estado, string $disco): void {
    expect((new RegraMoverFicheiro)->discoParaEstado($estado))->toBe($disco);
})->with([
    'pendente → entrada' => [EstadoDocumento::Pendente, 'entrada'],
    'análise malware → entrada' => [EstadoDocumento::AnaliseMalware, 'entrada'],
    'análise texto → entrada' => [EstadoDocumento::AnaliseTexto, 'entrada'],
    'análise ocr → entrada' => [EstadoDocumento::AnaliseOcr, 'entrada'],
    'análise ia local → enviado' => [EstadoDocumento::AnaliseIaLocal, 'enviado'],
    'análise cloud → enviado' => [EstadoDocumento::AnaliseCloud, 'enviado'],
    'processado → processado' => [EstadoDocumento::Processado, 'processado'],
    'erro → erro' => [EstadoDocumento::Erro, 'erro'],
    'perigoso → perigoso' => [EstadoDocumento::Perigoso, 'perigoso'],
]);

it('lança quando o ficheiro de origem não existe', function (): void {
    Storage::fake('enviado');
    Storage::fake('processado');

    expect(fn (): array => (new RegraMoverFicheiro)->handle('enviado', 'inexistente.pdf', EstadoDocumento::Processado))
        ->toThrow(RuntimeException::class, 'Ficheiro de origem inexistente.');
});

it('lança quando o rename no mesmo disco falha', function (): void {
    Storage::fake('processado');

    expect(fn (): array => (new RegraMoverFicheiro)->handle('processado', 'inexistente.pdf', EstadoDocumento::Processado, 'novo.pdf'))
        ->toThrow(RuntimeException::class, 'Falha ao renomear o ficheiro no disco.');
});

it('lança quando a escrita no disco de destino falha', function (): void {
    $origem = Mockery::mock(Filesystem::class);
    $origem->shouldReceive('get')->with('doc.pdf')->andReturn('conteudo');

    $destino = Mockery::mock(Filesystem::class);
    $destino->shouldReceive('put')->with('doc.pdf', 'conteudo')->andReturnFalse();

    Storage::shouldReceive('disk')->with('entrada')->andReturn($origem);
    Storage::shouldReceive('disk')->with('enviado')->andReturn($destino);

    expect(fn (): array => (new RegraMoverFicheiro)->handle('entrada', 'doc.pdf', EstadoDocumento::AnaliseIaLocal))
        ->toThrow(RuntimeException::class, 'Falha ao escrever o ficheiro no disco de destino.');
});

it('compensa (apaga o destino) e lança quando a remoção da origem falha', function (): void {
    $origem = Mockery::mock(Filesystem::class);
    $origem->shouldReceive('get')->with('doc.pdf')->andReturn('conteudo');
    $origem->shouldReceive('delete')->with('doc.pdf')->andReturnFalse();

    $destino = Mockery::mock(Filesystem::class);
    $destino->shouldReceive('put')->with('doc.pdf', 'conteudo')->andReturnTrue();
    $destino->shouldReceive('delete')->with('doc.pdf')->andReturnTrue();

    Storage::shouldReceive('disk')->with('entrada')->andReturn($origem);
    Storage::shouldReceive('disk')->with('enviado')->andReturn($destino);

    expect(fn (): array => (new RegraMoverFicheiro)->handle('entrada', 'doc.pdf', EstadoDocumento::AnaliseIaLocal))
        ->toThrow(RuntimeException::class, 'Falha ao remover o ficheiro da origem.');

    $destino->shouldHaveReceived('delete')->with('doc.pdf');
});
