# Plano: Extração — extractores de texto (pdfparser nativo + Tesseract OCR)

**Issue:** #96
**Spec:** docs/specs/2026-07-14-extractores-texto-extracao.md
**Data:** 2026-07-14

## Tarefas

### Tarefa 1 — Config: `ocr.dpi` e `ocr.linguas`

- Ficheiros a criar/alterar: `config/extracao.php`, `tests/Unit/Config/ExtracaoConfigTest.php`
- O que implementar: adicionar a `config/extracao.php` a chave `'ocr' => ['dpi' => 300, 'linguas' => ['por', 'eng']]`. Sem novas env vars (valores fixos, mesmo padrão de `threshold_caracteres`/`max_tentativas`).
- Testes associados: um novo `it()` em `ExtracaoConfigTest.php` (`'define os valores fixos de dpi e línguas do ocr'`) — mesmo estilo do teste de threshold/ttl/tentativas já existente.
- Commit: `feat(extracao): adiciona parâmetros ocr.dpi e ocr.linguas à config de extração`

### Tarefa 2 — `ResultadoExtracao` (VO) + `FalhaExtracaoTextoException`

- Ficheiros a criar: `app/Infrastructure/Extracao/ResultadoExtracao.php`, `app/Infrastructure/Extracao/FalhaExtracaoTextoException.php`
- O que implementar:
  - `ResultadoExtracao`: `final readonly class` com `texto: string` e `ultrapassaThreshold: ?bool`. Construtor privado (invariante: `texto` pode ser vazio — vazio é um resultado válido, ex. PDF em branco — sem `InvalidArgumentException` de "vazio"). Factories estáticas: `comVeredictoThreshold(string $texto, bool $ultrapassaThreshold): self` (nativo) e `semVeredicto(string $texto): self` (OCR, `ultrapassaThreshold = null`). Getters: `texto(): string`, `ultrapassaThreshold(): ?bool`.
  - `FalhaExtracaoTextoException`: `final class extends \RuntimeException` — mesmo padrão de `FalhaAnaliseMalwareException` (docblock a explicar quando é lançada: falha técnica de pdfparser/tesseract/ghostscript/imagick, nunca para "vazio"/"abaixo do threshold", que são resultados válidos).
- Testes associados: `tests/Unit/Infrastructure/Extracao/ResultadoExtracaoTest.php` — cobre as duas factories, getters, e o caso `texto` vazio (válido). Sem teste dedicado para a excepção (classe trivial, sem lógica — mesmo critério de `FalhaAnaliseMalwareException`, sem teste próprio).
- Commit: `feat(extracao): adiciona VO ResultadoExtracao e FalhaExtracaoTextoException`

### Tarefa 3 — `ExtractorTextoNativo` (pdfparser)

- Ficheiros a criar: `app/Infrastructure/Extracao/ExtractorTextoNativo.php`, `tests/Unit/Infrastructure/Extracao/ExtractorTextoNativoTest.php`, fixture `tests/Fixtures/Extracao/pdf-digital.pdf` (PDF real com texto > 50 caracteres, gerado uma vez e commitado — pequeno, PII-free, texto genérico tipo "Lorem ipsum" ou frase de teste em PT), fixture `tests/Fixtures/Extracao/pdf-corrompido.pdf` (bytes inválidos, não é PDF válido).
- O que implementar: `final readonly class ExtractorTextoNativo` — método público `extrair(string $caminhoAbsoluto): ResultadoExtracao`. Usa `\Smalot\PdfParser\Parser` (`parseFile($caminho)` → `getText()`), aplica a regra dos 50 caracteres via `config('extracao.threshold_caracteres')` e devolve `ResultadoExtracao::comVeredictoThreshold(...)`. Falha do parser (ficheiro corrompido/não-PDF) → captura a excepção do pdfparser e relança como `FalhaExtracaoTextoException` (`@throws` declarado).
- Testes associados:
  - Happy path: fixture `pdf-digital.pdf` → texto não vazio, `ultrapassaThreshold: true`.
  - Threshold: fixture com texto curto (< 50 chars, pode ser um PDF fixture adicional `pdf-digital-curto.pdf` ou gerado em runtime com `smalot/pdfparser`'s writer não existe — mais simples: fixture estática pequena) → `ultrapassaThreshold: false`.
  - Ficheiro corrompido (`pdf-corrompido.pdf`) → `FalhaExtracaoTextoException`.
- Commit: `feat(extracao): adiciona ExtractorTextoNativo (pdfparser)`

### Tarefa 4 — `ExtractorOcr` (imagick + Tesseract)

- Ficheiros a criar: `app/Infrastructure/Extracao/ExtractorOcr.php`, `tests/Unit/Infrastructure/Extracao/ExtractorOcrTest.php`, helper de fixture `tests/Support/gera_pdf_imagem.php` (gera em runtime, via `imagick`, um PDF multi-página sem camada de texto — desenha texto conhecido como imagem raster e grava como PDF; evita commitar um binário grande/frágil e garante texto conhecido para a asserção de substring).
- O que implementar: `final readonly class ExtractorOcr` — método público `extrair(string $caminhoAbsoluto): ResultadoExtracao`.
  - Abre o PDF com `\Imagick`, `setResolution($dpi, $dpi)` **antes** de `readImage()` (ordem exigida pelo Imagick/Ghostscript para rasterização correcta a 300 DPI), itera páginas.
  - Por página: grava imagem temporária em `storage_path('app/temp/<uuid>-pagina-N.png')`, corre `(new \thiagoalessio\TesseractOCR\TesseractOCR($caminhoImagem))->lang(...config('extracao.ocr.linguas'))->run()`, concatena ao texto acumulado, `unlink()` da imagem da página imediatamente após o OCR, `clear()`+`destroy()` do objecto `Imagick` da página (não só do documento).
  - Bloco `finally` ao nível do método público: percorre e remove quaisquer temporários remanescentes desta execução (nome prefixado por um UUID único da chamada, para não apagar temporários de execuções concorrentes) e `destroy()` do `Imagick` do documento.
  - Falha (imagick não consegue ler o ficheiro, tesseract falha) → captura e relança `FalhaExtracaoTextoException`.
  - Devolve `ResultadoExtracao::semVeredicto($textoConcatenado)`.
- Testes associados:
  - Happy path: fixture gerada (2 páginas, texto conhecido por página) → texto devolvido contém as palavras-chave conhecidas de cada página (substring, não igualdade — RNF-05/CA-07).
  - Limpeza: após a chamada (sucesso), `storage/app/temp/` não contém nenhum ficheiro com o prefixo desta execução.
  - Limpeza em falha: força falha a meio (ex.: ficheiro corrompido) → `storage/app/temp/` continua sem temporários residuais (CA-03).
  - Libertação por página (CA-06): PDF fixture de 3+ páginas — durante uma execução instrumentada (ex.: spy simples via contagem de ficheiros em disco a meio da iteração não é trivial de observar externamente sem alterar a assinatura; alternativa: teste de memória não é fiável em CI. Decisão de implementação: verificar indirectamente — nenhum ficheiro de página fica em disco **entre** iterações, só o da página corrente existe momentaneamente; teste com PDF multi-página e uma dupla de asserções antes/depois não é observável de fora. Ver "Riscos de implementação" — CA-06 verificado por revisão de código (`clear()`/`destroy()`/`unlink()` dentro do loop, não fora) em vez de teste automatizado; documentar esta decisão no Debrief.)
  - Ficheiro corrompido → `FalhaExtracaoTextoException`, sem temporários (mesma fixture `pdf-corrompido.pdf` da Tarefa 3).
- Commit: `feat(extracao): adiciona ExtractorOcr (imagick + tesseract)`

## Ordem de implementação

1. Tarefa 1 (config) — os extractores das Tarefas 3/4 leem `config('extracao.*')`.
2. Tarefa 2 (`ResultadoExtracao`/`FalhaExtracaoTextoException`) — contrato de saída partilhado pelos dois extractores.
3. Tarefa 3 (`ExtractorTextoNativo`) — mais simples (sem processo externo, sem temporários), valida o padrão antes do OCR.
4. Tarefa 4 (`ExtractorOcr`) — depende de `ResultadoExtracao`/`FalhaExtracaoTextoException` (Tarefa 2) e da config (Tarefa 1); reusa a fixture `pdf-corrompido.pdf` da Tarefa 3.

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| dpi/línguas fixos | unit | `tests/Unit/Config/ExtracaoConfigTest.php` | `config('extracao.ocr.dpi')` = 300, `linguas` = `['por','eng']` |
| Factories + getters | unit | `tests/Unit/Infrastructure/Extracao/ResultadoExtracaoTest.php` | `comVeredictoThreshold()`, `semVeredicto()`, texto vazio válido |
| Happy path nativo | unit | `tests/Unit/Infrastructure/Extracao/ExtractorTextoNativoTest.php` | texto extraído, `ultrapassaThreshold: true` |
| Threshold nativo | unit | idem | `ultrapassaThreshold: false` para texto curto |
| Corrompido nativo | unit | idem | `FalhaExtracaoTextoException` |
| Happy path OCR | unit | `tests/Unit/Infrastructure/Extracao/ExtractorOcrTest.php` | texto reconhecido contém palavras-chave por página |
| Limpeza sucesso OCR | unit | idem | `storage/app/temp/` sem resíduos após sucesso |
| Limpeza falha OCR | unit | idem | `storage/app/temp/` sem resíduos após excepção |
| Corrompido OCR | unit | idem | `FalhaExtracaoTextoException` |

Sem testes em `tests/Feature/Features/` — não há Action nem endpoint HTTP nesta issue (RNF-02).

## Dependências

- Issues bloqueantes: nenhuma (`#95` já publicada em `main`).
- Deve ser implementada após: `#95`.

## Riscos de implementação

> Consolidados do Brief (`## Riscos identificados`) e da Spec — não apagar riscos do Brief.

- Fixture OCR determinística e não-flaky entre ambientes (host macOS vs. Docker Alpine) — mitigado
  por asserção de substring/palavra-chave, não igualdade exacta (CA-07).
- Memória em rasterização 300 DPI — mitigado por `clear()`/`destroy()` por página dentro do loop
  (RF-04), mas **CA-06 não tem verificação automatizada directa** (só revisão de código) — risco
  aceite e documentado, revisitar se surgir OOM real em produção.
- `thiagoalessio/tesseract_ocr` invoca `proc_open` directamente (sem `Process` facade) — testes
  correm o binário real, sem mock, dependem de `tesseract`/`ghostscript`/`imagick` instalados
  (confirmados no host e no `Dockerfile`).
- Reaproveitar `storage/app/temp/` sem disco Laravel registado — sem `Storage::fake()`; os testes
  usam o directório real e limpam explicitamente no `afterEach` como rede de segurança adicional
  (para além da limpeza do próprio `ExtractorOcr`).

## O que NÃO fazer nesta issue

- Não implementar orquestração/Schedule nem decisão de transição de `etapa_extracao` (Issue IV).
- Não chamar `RegistarEtapaExtracaoAction` nem gravar nada em `extracoes_documento`.
- Não implementar o cliente IA/Prism nem parsing de resposta estruturada (Issue III).
- Não criar interface `ContratoExtractorTexto` (decidido na Spec: sem interface).
- Não criar novo disco `tmp` em `config/filesystems.php` (decidido na Spec: reaproveitar `storage/app/temp/`).
- Não alterar `Dockerfile`/`composer.json` (infra já entregue pela `#95`).
