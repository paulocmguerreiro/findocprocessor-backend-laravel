# Debrief: Extração — extractores de texto (pdfparser nativo + Tesseract OCR)

**Issue:** #96
**Branch:** feat/extractores-texto-extracao
**Data:** 2026-07-14
**Commits:** 6 commits

## O que foi implementado

Segunda das 4 issues do mecanismo de extração por IA — dois serviços isolados em
`app/Infrastructure/Extracao/` que convertem um PDF em texto, sem qualquer escrita em BD nem
chamada a LLM:

- `ExtractorTextoNativo` — extrai o texto de um PDF digital via `smalot/pdfparser` e aplica a
  regra dos 50 caracteres (`config('extracao.threshold_caracteres')`), devolvendo o veredicto no
  `ResultadoExtracao`.
- `ExtractorOcr` — rasteriza cada página do PDF via `imagick` a `config('extracao.ocr.dpi')` DPI
  (300), corre `thiagoalessio/tesseract_ocr` com as línguas de `config('extracao.ocr.linguas')`
  (`por`+`eng`) sobre cada imagem e concatena o texto. Liberta a memória do `Imagick` por página
  (não só no fim) e garante, num bloco `finally`, a remoção de todos os temporários de
  `storage/app/temp/` mesmo em falha a meio.
- `ResultadoExtracao` — Value Object (`final readonly`, construtor privado) com `texto` e
  `ultrapassaThreshold` (`?bool`), construído via `comVeredictoThreshold()` (nativo) ou
  `semVeredicto()` (OCR, sempre `null`).
- `FalhaExtracaoTextoException` — excepção única partilhada pelos dois extractores para qualquer
  falha técnica (ficheiro corrompido, falha do `tesseract`/Ghostscript/`imagick`).
- `config/extracao.php` — novos parâmetros `ocr.dpi` (300) e `ocr.linguas` (`['por', 'eng']`).
- Correcção adicional (fora do âmbito original, decisão de checkpoint durante a Fase 2): o helper
  de fixture `tests/Support/gera_pdf_imagem.php` usava `exec()` para invocar o Ghostscript — foi
  substituído por `Illuminate\Support\Facades\Process::run()` com array de argumentos, eliminando a
  invocação de shell (achado do `checkpoint:scan`, WRN-016).

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `app/Infrastructure/Extracao/ExtractorTextoNativo.php` | criado | `smalot/pdfparser` + threshold |
| `app/Infrastructure/Extracao/ExtractorOcr.php` | criado | `imagick` + `tesseract_ocr`, limpeza por página |
| `app/Infrastructure/Extracao/ResultadoExtracao.php` | criado | VO, factories estáticas |
| `app/Infrastructure/Extracao/FalhaExtracaoTextoException.php` | criado | excepção única partilhada |
| `config/extracao.php` | alterado | + `ocr.dpi`, `ocr.linguas` |
| `phpstan.neon` | alterado | + `stubFiles: stubs/TesseractOCR.stub.php` |
| `stubs/TesseractOCR.stub.php` | criado | stub para o Larastan reconhecer a API fluente do pacote |
| `tests/ArchTest.php` | alterado | `App\Infrastructure\Extracao` adicionado ao `ignoring()` |
| `tests/Support/gera_pdf_imagem.php` | criado | gera fixture PDF-imagem via PostScript + Ghostscript + `imagick`; usa `Process::run()` (sem shell) |
| `tests/Fixtures/Extracao/pdf-digital.pdf` | criado | fixture PDF digital (> 50 chars) |
| `tests/Fixtures/Extracao/pdf-digital-curto.pdf` | criado | fixture PDF digital (< 50 chars, threshold falha) |
| `tests/Fixtures/Extracao/pdf-corrompido.pdf` | criado | fixture inválida, para CA-03 |
| `tests/Unit/Infrastructure/Extracao/ExtractorTextoNativoTest.php` | criado | CA-01 |
| `tests/Unit/Infrastructure/Extracao/ExtractorOcrTest.php` | criado | CA-02/CA-03/CA-06/CA-07 |
| `tests/Unit/Infrastructure/Extracao/ResultadoExtracaoTest.php` | criado | testa as duas factories |
| `tests/Unit/Config/ExtracaoConfigTest.php` | alterado | + asserções `ocr.dpi`/`ocr.linguas` |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| Sem interface comum entre os dois extractores | `ContratoExtractorTexto` polimórfico | Sem substituição prevista — o orquestrador (Issue IV) invoca sempre os dois, em sequência condicional, nunca um no lugar do outro (`padroes-acoes.md`) |
| Um único VO `ResultadoExtracao` para os dois extractores | VOs distintos por extractor | `ultrapassaThreshold: ?bool` cobre os dois casos (preenchido no nativo, `null` no OCR) sem duplicar estrutura |
| DPI e línguas via `config/extracao.php` | Hardcoded (`const`) no `ExtractorOcr` | Mantém consistência com o resto de `extracao.php`; permite ajuste sem alterar código |
| Reaproveitar `storage/app/temp/` | Novo disco `tmp` em `config/filesystems.php` | Já presente no `.gitignore`; scratch space de processo, não é um disco de ciclo de vida do `Documento` — não precisa de `Storage::fake()` |
| `FalhaExtracaoTextoException` única e partilhada | Uma excepção por extractor (pdfparser vs. tesseract vs. imagick) | Issue IV só precisa saber "falhou, contar tentativa"; a origem fica na mensagem, não no tipo (mesmo padrão de `FalhaAnaliseMalwareException`) |
| Testes sem mock do motor OCR/Ghostscript — binários reais | Mockar `TesseractOCR`/`Imagick` | Paridade com `ClamAvAnalisadorMalwareTest` (protocolo de baixo nível real, sem rede envolvida) |
| Asserção por substring/palavra-chave no texto OCR (CA-07) | Igualdade exacta da string devolvida | Evita flakiness entre motores Tesseract/versões de dados de línguas em ambientes diferentes (host macOS vs. Docker Alpine) |
| `exec()` → `Process::run()` no helper de fixture | Manter `exec()` com `escapeshellarg()` | Achado do `checkpoint:scan` (Command Injection Risks) — falso positivo na prática (sem input externo), mas eliminado o padrão por decisão do utilizador |

## Desvios ao Plano

Nenhum desvio funcional. Um ajuste de segurança fora do âmbito original da issue: substituição de
`exec()` por `Process::run()` em `tests/Support/gera_pdf_imagem.php`, motivada pelo
`checkpoint:scan` no fecho da Fase 2 (ver WRN-016 em `docs/process-warnings.md`).

## Aprendizagens

A dupla `ExtractorTextoNativo`/`ExtractorOcr` é o primeiro caso no projecto de dois serviços de
`Infrastructure/` que partilham só um VO de saída e uma excepção, sem qualquer interface comum —
reforça que "injectar interface quando há substituição prevista" (`padroes-acoes.md`) é sobre
substituição real (Repository, API externa trocável), não sobre "dois serviços parecidos". Ficou
também mais claro o padrão de limpeza de recursos em `finally` a dois níveis (por página, dentro do
loop, e ao nível do método público) — necessário porque o `Imagick` acumula memória nativa (fora do
GC do PHP) e uma falha a meio de uma página não pode deixar as páginas anteriores por libertar. Por
fim, o Larastan exigiu um stub próprio (`stubs/TesseractOCR.stub.php`) para reconhecer a API fluente
de um pacote de terceiros sem tipos — primeira vez que o projecto precisou deste mecanismo.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/04-infra/external-apis.md` — nova secção "Extractores de texto"
  (`ExtractorTextoNativo`/`ExtractorOcr`/`ResultadoExtracao`/`FalhaExtracaoTextoException`), tabela
  `Implementado`.
- `docs/system_spec/00-index.md` — linha da tabela `Infra` (`APIs externas (IA)`) actualizada.
- `docs/system_spec/06-config.md` — documentar `extracao.ocr.dpi` e `extracao.ocr.linguas`.

## Verificação final

- [x] Linter a verde (Pint + Rector, `composer test:lint`)
- [x] Testes a verde (987 + 8 arch, 100% coverage/types, Larastan nível 9 zero erros — via Docker/MySQL)
- [x] Nenhum dado sensível em logs (sem logging do texto extraído nesta camada)
- [x] Nenhum segredo em código
