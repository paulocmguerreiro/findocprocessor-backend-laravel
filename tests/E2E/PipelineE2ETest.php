<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Database\Seeders\SimulacaoPipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Teste end-to-end do pipeline de extração contra os serviços REAIS (Tesseract,
 * Ollama e — se disponível — ClamAV). NÃO faz parte do `composer test` (vive fora
 * de tests/Unit|Feature); corre com `composer test:e2e`. Faz skip automático se o
 * Ollama ou o Tesseract não responderem, pelo que nunca parte um CI sem esses
 * serviços. Usa fixtures A4 realistas em tests/Fixtures/faturas (ver gerar.sh).
 *
 * Modelo local: env E2E_LLM_MODEL (default 'qwen2.5:7b-instruct'). Com a extracção
 * role-neutral (emissor/destinatário) + resolução de papéis por NIF, os três cenários
 * — incluindo "venda" (mãe=fornecedor/emitente) — passam nesse modelo. Modelos mais
 * pequenos (ex.: 'qwen2.5:3b') tendem a omitir campos e escalam para a cloud; por isso
 * o default é o 7b, único fiável em todos os campos.
 */
uses(TestCase::class, RefreshDatabase::class);

/** Testa se um serviço TCP está a aceitar ligações (skip do teste se não). */
function tcpAberto(string $host, int $porta, float $timeout = 1.5): bool
{
    $ligacao = @fsockopen($host, $porta, $errno, $errstr, $timeout);

    if ($ligacao === false) {
        return false;
    }

    fclose($ligacao);

    return true;
}

function ollamaDisponivel(string $url): bool
{
    $partes = parse_url($url);
    $host = is_array($partes) ? ($partes['host'] ?? '127.0.0.1') : '127.0.0.1';
    $porta = is_array($partes) ? (int) ($partes['port'] ?? 11434) : 11434;

    return tcpAberto($host, $porta);
}

function tesseractDisponivel(): bool
{
    return trim((string) shell_exec('command -v tesseract 2>/dev/null')) !== '';
}

beforeEach(function (): void {
    $modelo = (string) env('E2E_LLM_MODEL', 'qwen2.5:7b-instruct');
    $url = (string) env('E2E_LLM_URL', 'http://localhost:11434');

    if (! ollamaDisponivel($url)) {
        $this->markTestSkipped("Ollama não disponível em {$url} — E2E ignorado.");
    }

    if (! tesseractDisponivel()) {
        $this->markTestSkipped('Tesseract não instalado — E2E ignorado.');
    }

    // Camada local activa, apontada ao modelo/URL do E2E, com timeout generoso
    // (modelos locais podem demorar dezenas de segundos por documento).
    config([
        'extracao.local' => ['provider' => 'ollama', 'modelo' => $modelo, 'url' => $url, 'activa' => true],
        'extracao.timeout_segundos' => (int) env('E2E_LLM_TIMEOUT', 180),
    ]);

    // ClamAV é opcional: se não estiver de pé, desliga a camada (fail-safe) para o
    // E2E se focar em OCR + LLM em vez de falhar na triagem de malware.
    $clamHost = (string) config('pipeline.malware.host');
    $clamPort = (int) config('pipeline.malware.port');
    if ($clamHost === '' || $clamPort === 0 || ! tcpAberto($clamHost, $clamPort)) {
        config(['pipeline.malware.host' => '', 'pipeline.malware.port' => 0]);
    }

    // Discos isolados (temporários) — não toca no storage real.
    foreach (['entrada', 'enviado', 'processado', 'erro', 'perigoso'] as $disco) {
        Storage::fake($disco);
    }

    $this->seed(SimulacaoPipelineSeeder::class);
    $this->withoutMiddleware(ThrottleRequests::class);
    criarEAutenticarAdmin();
});

/**
 * Faz upload do fixture e conduz o pipeline (scan → texto/OCR → IA local) até um
 * estado terminal, correndo cada comando `extracao:*` até o documento estabilizar.
 */
function processarFixture(string $ficheiro): Documento
{
    $caminho = base_path("tests/Fixtures/faturas/{$ficheiro}");
    $upload = new UploadedFile($caminho, $ficheiro, 'image/png', null, true);

    $resposta = test()->post('/api/documentos/upload', ['ficheiro' => $upload], ['Accept' => 'application/json']);
    $resposta->assertCreated();

    // O middleware auth:sanctum deixou o guard sanctum como default; o pipeline corre
    // em consola (guard web) e faz Auth::login() do responsável — repõe o guard web.
    app('auth')->shouldUse('web');

    /** @var string $id */
    $id = $resposta->json('data.id');
    $documento = Documento::findOrFail($id);

    $terminais = [EstadoDocumento::Processado, EstadoDocumento::Erro, EstadoDocumento::Perigoso, EstadoDocumento::AnaliseCloud];

    for ($iteracao = 0; $iteracao < 8; $iteracao++) {
        $documento->refresh();

        if (in_array($documento->estado, $terminais, true)) {
            break;
        }

        Artisan::call('extracao:run-scan');
        Artisan::call('extracao:run-parser');
        Artisan::call('extracao:run-tesseract');
        Artisan::call('extracao:run-ia-local');
    }

    return $documento->refresh();
}

it('processa uma fatura A4 até PROCESSADO com os dados extraídos correctos', function (array $cenario): void {
    $documento = processarFixture($cenario['ficheiro']);

    expect($documento->estado)->toBe(EstadoDocumento::Processado)
        ->and($documento->fornecedor?->nif)->toBe($cenario['fornecedor_nif'])
        ->and($documento->cliente?->nif)->toBe($cenario['cliente_nif'])
        ->and((float) $documento->valor)->toBe($cenario['valor'])
        ->and($documento->data_documento?->format('Y-m-d'))->toBe($cenario['data'])
        ->and($documento->categoria?->nome)->toBe($cenario['categoria']);
})->with([
    // Empresa mãe (NIF 501234567) é o CLIENTE; extrai o fornecedor emitente.
    'compra (mãe=cliente)' => [[
        'ficheiro' => 'fatura-compra.png',
        'fornecedor_nif' => '502777888',
        'cliente_nif' => '501234567',
        'valor' => 151.84,
        'data' => '2026-07-15',
        'categoria' => 'Compras e Serviços',
    ]],
    // Empresa mãe é o CLIENTE; extrai fornecedor E cliente (o cliente resolve para a mãe).
    'serviços (extrai ambos)' => [[
        'ficheiro' => 'fatura-servicos.png',
        'fornecedor_nif' => '504111222',
        'cliente_nif' => '501234567',
        'valor' => 2091.00,
        'data' => '2026-07-20',
        'categoria' => 'Compras e Serviços',
    ]],
    // Empresa mãe é o FORNECEDOR (emitente/letterhead); extrai o cliente destinatário.
    // A resolução por NIF põe a mãe no lado fornecedor e corrige o tipo/categoria para Vendas.
    'venda (mãe=fornecedor)' => [[
        'ficheiro' => 'fatura-venda.png',
        'fornecedor_nif' => '501234567',
        'cliente_nif' => '503888999',
        'valor' => 953.25,
        'data' => '2026-07-18',
        'categoria' => 'Vendas',
    ]],
]);
