# Brief: Extração — extractores de texto (pdfparser nativo + Tesseract OCR)

**Issue:** #96
**Data:** 2026-07-14
**Branch:** feat/extractores-texto-extracao

## Contexto

Segunda das 4 issues do mecanismo de extração por IA (`#95` infra → **`#96` extractores** →
Issue III cliente IA → Issue IV orquestração). Produz a matéria-prima (`texto_extraido`) que o
cliente IA (Issue III) vai interpretar. Os dois extractores — nativo (`smalot/pdfparser`, PDF
digital) e OCR (`thiagoalessio/tesseract_ocr` + `imagick`, PDF digitalizado/imagem) — são serviços
isolados de `app/Infrastructure/`, invocáveis e testáveis sem o pipeline. A decisão de transição de
estado (`etapa_extracao`), a reivindicação/lease e a contagem de tentativas ficam para a Issue IV
(orquestrador); esta issue só devolve o texto (ou lança excepção tipada em falha técnica).

`#95` já entregou os pré-requisitos: pacotes Composer (`smalot/pdfparser` `^2.12`,
`thiagoalessio/tesseract_ocr` `^2.13`), `imagick`/`gd`/`tesseract-ocr` (línguas `por`+`eng`)/
`ghostscript` na imagem Docker (`Dockerfile`), e `config/extracao.php` (`threshold_caracteres` = 50).
Confirmado também no host de desenvolvimento (`tesseract`, `convert`, `gs`, extensões `imagick`/`gd`
presentes) — paridade host/Docker para os binários externos.

## O que muda

- **`app/Infrastructure/Extracao/`** (novo subdirectório, ao lado de `Malware/` e `AI/`):
  - `ExtractorTextoNativo` — `smalot/pdfparser`; devolve o texto de um PDF digital; aplica a regra
    dos 50 caracteres (`strlen(trim($texto)) > 50`) usando `config('extracao.threshold_caracteres')`
    e devolve o veredicto (não decide a transição — isso é da Issue IV).
  - `ExtractorOcr` — rasteriza PDF via `imagick` a 300 DPI (`setResolution(300, 300)`) para imagens
    temporárias, corre `thiagoalessio/tesseract_ocr` (línguas `por`+`eng`) por página, concatena o
    texto. Limpeza garantida dos temporários e libertação da memória do `imagick`
    (`clear()`/`destroy()`) num bloco `finally`, mesmo em falha.
  - VO de resultado (`ResultadoExtracao` ou nome equivalente — texto + origem/veredicto do
    threshold), seguindo o padrão Value Object já usado em `ResultadoAnaliseMalware`
    (`02-shared/padroes-dtos.md` adaptado — não é `fromRequest()`, é resultado de infra, não input
    HTTP).
  - Excepção tipada de falha técnica (ficheiro corrompido, Tesseract/Ghostscript falha) — mesmo
    padrão de `FalhaAnaliseMalwareException` (`final class ... extends RuntimeException`).
  - Interface comum entre os dois extractores só se a Spec concluir que faz sentido (ver "Questões
    em aberto" — os dois não são substituíveis um pelo outro, o orquestrador invoca-os
    explicitamente em sequência, ao contrário do padrão Repository/Service com substituição
    prevista).
- **`config/filesystems.php`** — possível novo disco `tmp` (`storage/app/tmp/`) para as imagens
  rasterizadas, fakeable via `Storage::fake('tmp')` (critério de aceitação exige teste da limpeza).
  Detalhe da decisão (disco Laravel vs. `storage_path()` directo) fica para a Spec.
- **Testes:** `tests/Unit/Infrastructure/Extracao/` (mesmo padrão de
  `tests/Unit/Infrastructure/Malware/`) — sem HTTP, sem `Feature/Features/` (não são Actions nem
  endpoints). Fixtures novas: um PDF digital com texto (> 50 caracteres), um PDF "digitalizado" sem
  camada de texto (gerado a partir de uma imagem, para exercitar o OCR) e um ficheiro corrompido.
- **`docs/system_spec/04-infra/external-apis.md`** — nova secção "Extractores de texto" (nível
  "implementado"), tabela `Implementado`, referência de `00-index.md`.
- **`docs/system_spec/00-index.md`** — linha da tabela `Infra` actualizada (ou nova linha, conforme
  a Spec decidir agrupar com `external-apis.md` ou criar ficheiro próprio).

## O que NÃO muda

- Orquestração/Schedule, decisão de transição de `etapa_extracao`, lease de reivindicação e
  contagem de tentativas (`extracao_reclamada_em`, `extracao_tentativas`) — tudo Issue IV.
- `RegistarEtapaExtracaoAction` / `ExtracaoDocumento` — nenhuma escrita em BD nesta issue; os
  extractores são funções puras sobre um ficheiro em disco → texto.
- Cliente IA / prompt / nonce (Issue III) — sem chamadas a LLM nesta issue (critério de aceitação
  explícito).
- Pipeline de `Documento` (`ReivindicarDocumentoPendenteAction`, `TriarDocumentoPendenteAction`,
  etc.) — não invocado nem alterado.
- `config/extracao.php` (`threshold_caracteres`, `ttl_lease`, `max_tentativas`, flags LLM) — já
  existe da `#95`, só consumido (leitura), sem novos parâmetros previstos.
- Infra Docker/pacotes Composer — já entregues pela `#95`; esta issue não toca no `Dockerfile` nem
  em `composer.json` salvo se a Spec decidir que faltou algo (não esperado, já confirmado presente).

## Riscos identificados

- **Fixture OCR determinística e não-flaky:** o critério de aceitação exige um PDF-imagem fixture
  real (sem camada de texto) e a leitura via Tesseract real (sem mock — RNF equivalente ao "sem
  `clamd` real" da Malware não se aplica aqui, o motor OCR corre local, não há rede). Texto OCR de
  imagens geradas em runtime pode ter pequenas variações de reconhecimento; o teste deve verificar
  uma substring/palavra-chave robusta, não uma igualdade exacta, para não ficar flaky entre
  ambientes (host macOS vs. Alpine Docker, versões de `tesseract-ocr-data-por`/`eng` diferentes).
- **Memória em rasterização 300 DPI (M1 8GB):** requisito explícito da issue — sem libertação
  correcta do `Imagick` (`clear()`/`destroy()` por página, não só no fim) o consumo de memória em
  PDFs multi-página pode escalar. A Spec deve detalhar o padrão de libertação por página, não só um
  `finally` genérico no fim do método.
- **Disco `tmp` inexistente em `config/filesystems.php`:** o `.gitignore` já tem uma entrada
  `storage/app/temp/*` (nome diferente do `storage/app/tmp/` pedido pela issue) que não corresponde
  a nenhum disco configurado — resíduo de scaffold anterior, não usado pelos 5 discos de ciclo de
  vida actuals (`entrada`/`enviado`/`processado`/`erro`/`perigoso`). Decisão a tomar na Spec: criar
  disco `tmp` novo (nome pedido pela issue) + entrada `.gitignore` própria, e avaliar se a entrada
  `temp` antiga deve ser removida (fora de âmbito se não tiver relação — confirmar antes de mexer).
- **Excepção partilhada vs. uma por extractor:** o padrão `FalhaAnaliseMalwareException` (uma única
  excepção para toda a infra de Malware) é o precedente mais próximo; replicar para uma
  `FalhaExtracaoTextoException` partilhada entre os dois extractores evita duplicação, mas perde
  granularidade (o caller não distingue "pdfparser falhou" de "tesseract falhou" sem inspeccionar a
  mensagem). A Issue IV só precisa de saber "falhou, contar tentativa" — não parece precisar de
  distinguir a origem. A decidir na Spec.
- **`thiagoalessio/tesseract_ocr` invoca o binário `tesseract` via `proc_open` (não `Symfony
  Process`/Laravel `Process` facade)** — sem integração nativa com `Process::fake()`; os testes têm
  de correr o binário real (paridade com o precedente `ClamAvAnalisadorMalwareTest`, que também não
  mocka o protocolo de baixo nível, só o "não configurado"/timeout).

## Questões em aberto

- **Interface comum (`ContratoExtractorTexto`) — sim ou não?** A issue deixa em aberto ("se fizer
  sentido"). Os dois extractores não são intercambiáveis (o orquestrador da Issue IV chama sempre
  ambos, em sequência condicional — nativo primeiro, OCR se o threshold falhar), ao contrário do
  padrão Repository/Service com substituição prevista (`padroes-acoes.md`: injectar interface só
  quando há substituição prevista). Proposta: **sem interface**, só o VO `ResultadoExtracao`
  partilhado como contrato de saída comum — decidir definitivamente na Spec.
- **Nome e forma do VO de resultado:** `ResultadoExtracao` com `texto` + veredicto do threshold
  (nativo) vs. um VO mais simples (`texto` só, sem veredicto, deixando o cálculo do threshold para o
  chamador)? A issue pede "devolve-se o veredicto/threshold via config" no extractor nativo — mas o
  OCR não tem veredicto de threshold (é sempre o "último recurso" textual, a decisão de ir para
  "NecessitaCloud" depois do OCR é da Issue IV, não deste extractor). Confirmar na Spec se o VO é
  o mesmo para os dois extractores ou se cada um devolve algo ligeiramente diferente.
- **Localização da constante de DPI e línguas Tesseract:** `300` e `'por'+'eng'` — hardcoded no
  `ExtractorOcr` (como `TAMANHO_CHUNK` em `ClamAvAnalisadorMalware`) ou via `config/extracao.php`
  (novos parâmetros)? A issue não pede configurabilidade; proposta: hardcoded (`const`), sem novo
  parâmetro de config — confirmar.
- **Nome do disco/directório temporário exacto** (`storage/app/tmp/` conforme a issue, ou reusar
  `storage/app/temp/` já presente no `.gitignore`) — ver risco acima, resolver na Spec antes do
  Plano.
