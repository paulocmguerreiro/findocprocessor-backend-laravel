# System Spec — Infra: Extractores de Texto (pdfparser + OCR)

> `app/Infrastructure/Extracao/`

Extractores de texto (nativo + OCR) implementados, ver secção dedicada abaixo.

---

## Implementado

| Componente | Ficheiro | Estado |
|---|---|---|
| `ExtractorTextoNativo`/`ExtractorOcr` — extractores de texto de `Documento` | `app/Infrastructure/Extracao/` | implementado |

## Extractores de texto — pdfparser nativo + Tesseract OCR

Matéria-prima (`texto_extraido`) para o cliente IA (integração planeada em `04-infra/extracao-ia.md`). Dois serviços
isolados e puros em `app/Infrastructure/Extracao/` — recebem o caminho absoluto de um ficheiro e
devolvem texto, sem escrita em BD, sem chamada a LLM e sem dependência de `Documento`/
`ExtracaoDocumento`. A decisão de transição de estado (o passo de análise é o `Documento.estado`), a
reivindicação/lease e a contagem de tentativas ficam a cargo do orquestrador do pipeline (ver
`01-features/documento-pipeline.md`).

| Componente | Ficheiro | Papel |
|---|---|---|
| `ExtractorTextoNativo` | `ExtractorTextoNativo.php` | `extrair(string $caminhoAbsoluto): ResultadoExtracao` — texto de PDF digital via `smalot/pdfparser`, aplica o threshold de `config('extracao.threshold_caracteres')` |
| `ExtractorOcr` | `ExtractorOcr.php` | `extrair(string $caminhoAbsoluto): ResultadoExtracao` — rasteriza cada página via `imagick` a `config('extracao.ocr.dpi')` DPI e reconhece com `thiagoalessio/tesseract_ocr` (`config('extracao.ocr.linguas')`) |
| `ResultadoExtracao` (Value Object) | `ResultadoExtracao.php` | `comVeredictoThreshold(string $texto, bool $ultrapassaThreshold)`/`semVeredicto(string $texto)` — construtor privado |
| `FalhaExtracaoTextoException` | `FalhaExtracaoTextoException.php` | Única excepção de falha técnica, partilhada pelos dois extractores (ficheiro corrompido, falha do processo `tesseract`/Ghostscript/`imagick`) |

### Sem interface comum entre os dois extractores

Ao contrário do padrão Repository/Service (`02-shared/padroes-acoes.md`), não há substituição
prevista entre `ExtractorTextoNativo` e `ExtractorOcr` — nunca um no lugar do outro, o único contrato
de saída partilhado é o VO `ResultadoExtracao`. O orquestrador (`ProcessarAnaliseTextoDocumentoAction`,
`01-features/documento-pipeline.md`) decide qual invocar: PDF → `ExtractorTextoNativo` primeiro (OCR
só se o threshold falhar); **não-PDF** (imagem — JPG/PNG/TIFF/BMP/WEBP, #111) → salta directo para
`ExtractorOcr`, o parser nativo nunca é chamado (não há texto embutido para extrair de uma imagem).

### Delegates de imagem (TIFF/WEBP/BMP, #111)

`ExtractorOcr` rasteriza com `imagick` — os formatos de upload alargados (`image/tiff`, `image/bmp`,
`image/webp`, ver `01-features/documento.md`) dependem dos delegates correspondentes estarem
disponíveis no container. O Dockerfile instala `libwebp`/`tiff` via `apk` antes do
`install-php-extensions imagick` (BMP é suportado nativamente pelo ImageMagick/Leptonica, sem
delegate extra). Sem o delegate, o upload é aceite (a validação do `FormRequest` só olha ao
`mimetype`) mas a rasterização falha em runtime — o documento vai a `Erro` por falha técnica de OCR.

### `ultrapassaThreshold` — só o nativo decide

O threshold de 50 caracteres (`config('extracao.threshold_caracteres')`) só é calculado por
`ExtractorTextoNativo`. `ExtractorOcr` devolve sempre `ultrapassaThreshold: null` — o OCR é o
"último recurso" textual; decidir se o resultado é suficiente ou se avança para as camadas LLM é
responsabilidade do orquestrador do pipeline, não deste extractor.

### `ExtractorOcr` — limpeza de recursos a dois níveis

`Imagick` acumula memória nativa fora do GC do PHP. `ExtractorOcr` liberta `clear()`/`destroy()`
por página processada, dentro do loop de rasterização — não só no fim do método — e remove o
ficheiro temporário da página assim que deixa de ser necessário. Um bloco `finally` ao nível do
método público garante, adicionalmente, que qualquer temporário remanescente de
`storage/app/temp/<uuid-execução>-pagina-*.png` é removido mesmo em falha a meio (ex. página 3 de 5
falha o reconhecimento). Não existe disco Laravel registado para este directório — é scratch space
de processo, não um disco de ciclo de vida do `Documento`; acedido via `storage_path()` directo.

### Testes sem mock do motor OCR/Ghostscript (RNF-05)

Mesma decisão de `ClamAvAnalisadorMalwareTest` (`04-infra/malware.md`) — os testes de `ExtractorOcrTest` correm o
`tesseract`/`imagick`/Ghostscript reais (sem rede envolvida, motor local), sem `Mockery`. A fixture
de PDF-imagem é gerada em runtime (`tests/Support/gera_pdf_imagem.php`) via PostScript +
Ghostscript (invocado com `Illuminate\Support\Facades\Process::run()`, array de argumentos, sem
shell) + `imagick`, para não commitar um binário frágil. A asserção sobre o texto reconhecido usa
substring/palavra-chave (`toContain()`), nunca igualdade exacta — evita flakiness entre motores
Tesseract/dados de línguas diferentes consoante o ambiente (host vs. Docker).

### Stub Larastan para `thiagoalessio/tesseract_ocr`

Primeiro caso no projecto de um pacote de terceiros sem tipos suficientes para o Larastan nível 9
reconhecer a API fluente (`TesseractOCR::lang()->run()`). Resolvido com um stub próprio
(`stubs/TesseractOCR.stub.php`, registado em `phpstan.neon` → `stubFiles`), não com anotações
`@phpstan-ignore` dispersas no código de domínio.
