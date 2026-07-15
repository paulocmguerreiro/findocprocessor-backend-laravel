<?php

declare(strict_types=1);

use App\Infrastructure\Extracao\ExtractorOcr;
use App\Infrastructure\Extracao\FalhaExtracaoTextoException;
use App\Infrastructure\Extracao\ResultadoExtracao;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

require_once __DIR__.'/../../../Support/gera_pdf_imagem.php';

/**
 * @return list<string>
 */
function listarTemporariosOcr(): array
{
    return glob(storage_path('app/temp/*.png')) ?: [];
}

afterEach(function (): void {
    foreach (listarTemporariosOcr() as $ficheiro) {
        unlink($ficheiro);
    }
});

it('reconhece o texto de cada página de um pdf-imagem via ocr', function (): void {
    $caminho = storage_path('app/temp/'.Str::uuid()->toString().'.pdf');
    gera_pdf_imagem($caminho, ['PALAVRACHAVEUM primeira pagina', 'PALAVRACHAVEDOIS segunda pagina']);

    try {
        $resultado = (new ExtractorOcr)->extrair($caminho);

        expect($resultado->texto)->toContain('PALAVRACHAVEUM')
            ->and($resultado->texto)->toContain('PALAVRACHAVEDOIS')
            ->and($resultado->ultrapassaThreshold)->toBeNull();
    } finally {
        unlink($caminho);
    }
});

it('não deixa temporários em storage/app/temp/ após sucesso', function (): void {
    $caminho = storage_path('app/temp/'.Str::uuid()->toString().'.pdf');
    gera_pdf_imagem($caminho, ['PALAVRACHAVETRES pagina unica']);

    try {
        (new ExtractorOcr)->extrair($caminho);

        expect(listarTemporariosOcr())->toBeEmpty();
    } finally {
        unlink($caminho);
    }
});

it('não deixa temporários em storage/app/temp/ após falha', function (): void {
    expect(fn (): ResultadoExtracao => (new ExtractorOcr)->extrair(base_path('tests/Fixtures/Extracao/pdf-corrompido.pdf')))
        ->toThrow(FalhaExtracaoTextoException::class);

    expect(listarTemporariosOcr())->toBeEmpty();
});

it('lança FalhaExtracaoTextoException quando o ficheiro está corrompido', function (): void {
    expect(fn (): ResultadoExtracao => (new ExtractorOcr)->extrair(base_path('tests/Fixtures/Extracao/pdf-corrompido.pdf')))
        ->toThrow(FalhaExtracaoTextoException::class);
});

it('remove no finally temporários órfãos com o mesmo id de execução', function (): void {
    $idExecucao = '11111111-1111-1111-1111-111111111111';
    Str::createUuidsUsing(fn (): UuidInterface => Uuid::fromString($idExecucao));

    $orfao = storage_path("app/temp/{$idExecucao}-pagina-99.png");
    file_put_contents($orfao, 'residuo de uma execução anterior');

    $caminho = storage_path('app/temp/'.Str::uuid()->toString().'.pdf');
    gera_pdf_imagem($caminho, ['PALAVRACHAVEQUATRO pagina unica']);

    try {
        (new ExtractorOcr)->extrair($caminho);

        expect(file_exists($orfao))->toBeFalse();
    } finally {
        Str::createUuidsNormally();
        unlink($caminho);
    }
});
