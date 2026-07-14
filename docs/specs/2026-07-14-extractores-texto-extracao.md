# Spec: Extração — extractores de texto (pdfparser nativo + Tesseract OCR)

**Issue:** #96
**Brief:** docs/briefs/2026-07-14-extractores-texto-extracao.md
**Data:** 2026-07-14

## Requisitos funcionais

- RF-01: `ExtractorTextoNativo` recebe o caminho absoluto de um ficheiro PDF, usa
  `smalot/pdfparser` para extrair o texto de todas as páginas e devolve um `ResultadoExtracao`.
- RF-02: `ExtractorTextoNativo` aplica a regra dos 50 caracteres
  (`strlen(trim($texto)) > config('extracao.threshold_caracteres')`) e regista o veredicto
  (`ultrapassaThreshold: true|false`) no `ResultadoExtracao` devolvido — não decide nem grava
  transição de estado.
- RF-03: `ExtractorOcr` recebe o caminho absoluto de um ficheiro PDF, rasteriza cada página via
  `imagick` a `config('extracao.ocr.dpi')` DPI (`setResolution($dpi, $dpi)`) para imagens
  temporárias em `storage/app/temp/`, corre `thiagoalessio/tesseract_ocr` com as línguas de
  `config('extracao.ocr.linguas')` sobre cada imagem, concatena o texto de todas as páginas
  (separador `"\n\n"`) e devolve um `ResultadoExtracao` com `ultrapassaThreshold: null` (o OCR não
  aplica o threshold — essa decisão pós-OCR é da Issue IV).
- RF-04: `ExtractorOcr` liberta a memória do `Imagick` (`clear()` + `destroy()`) por página
  processada, não só no fim do método, e apaga os ficheiros temporários da página assim que deixam
  de ser necessários.
- RF-05: `ExtractorOcr` garante, num bloco `finally` ao nível do método público, que **todos** os
  temporários da execução (mesmo em falha a meio) são removidos de `storage/app/temp/` e que o
  `Imagick` do documento é libertado.
- RF-06: Falha técnica em qualquer dos dois extractores (ficheiro corrompido/ilegível, falha do
  processo `tesseract`/Ghostscript, falha de rasterização `imagick`) lança
  `FalhaExtracaoTextoException` (`RuntimeException`), partilhada entre os dois extractores.
- RF-07: `ResultadoExtracao` é um Value Object (`final readonly class`) com `texto: string` e
  `ultrapassaThreshold: ?bool`, construído só via factories estáticas (`comTexto()`/padrão análogo
  a `ResultadoAnaliseMalware`) — nunca com construtor público directo.

## Requisitos não funcionais

- RNF-01: Sem interface comum entre `ExtractorTextoNativo` e `ExtractorOcr` — não há substituição
  prevista entre os dois (o orquestrador da Issue IV invoca-os explicitamente, nunca via injecção
  polimórfica); alinhado com `02-shared/padroes-acoes.md`.
- RNF-02: Nenhuma escrita em BD, nenhuma chamada a LLM, nenhuma dependência de `Documento`/
  `ExtracaoDocumento` — os dois extractores são funções puras `caminho → ResultadoExtracao`,
  testáveis isoladamente (Vertical Slice, "invocáveis de forma isolada" — issue).
- RNF-03: `strict_types=1`, sem `mixed`, `@throws` declarado em todo o método público que lança
  excepção (Regra B, `02-shared/padroes-tipagem.md`).
- RNF-04: 100% code coverage e 100% type coverage (`composer test`) — sem excepção, mesmo para os
  ramos de limpeza `finally`.
- RNF-05: Testes sem mocks do motor OCR/Ghostscript — mesma decisão que
  `ClamAvAnalisadorMalwareTest` (protocolo de baixo nível real), o Tesseract/Imagick correm
  localmente (Docker e host, ambos confirmados com os binários instalados), sem rede envolvida.

## Modelo de dados

Não aplicável — sem migration, sem Model novo. Nenhuma alteração a `extracoes_documento`.

## Regras de negócio

- RN-01: O threshold de 50 caracteres só se aplica ao resultado do `ExtractorTextoNativo`; o
  `ExtractorOcr` nunca calcula nem devolve veredicto de threshold (fica sempre `null`).
- RN-02: A limpeza de temporários e libertação de memória do `imagick` são garantidas
  independentemente do resultado (sucesso ou excepção) — verificado por teste dedicado que força
  falha a meio do processamento e confirma `storage/app/temp/` vazio no fim.
- RN-03: `FalhaExtracaoTextoException` é a única excepção tipada de falha técnica desta camada —
  partilhada pelos dois extractores (mesmo padrão de `FalhaAnaliseMalwareException`), sem
  subclasses por origem (pdfparser vs. tesseract vs. imagick); a mensagem da excepção distingue a
  origem em texto livre, não em tipo.

## Dependências

- Issues bloqueantes: nenhuma — `#95` (infra: pacotes, binários Docker, `config/extracao.php`) já
  está publicada (`main`), pré-requisito satisfeito.

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------- | ------- |
| Interface comum (`ContratoExtractorTexto`) — sim ou não? | **Não.** Sem substituição prevista entre os dois extractores; só o VO `ResultadoExtracao` é o contrato de saída comum (RNF-01). |
| Nome e forma do VO de resultado | **Mesmo VO**, `ResultadoExtracao` com `ultrapassaThreshold` opcional (`?bool`) — preenchido pelo nativo, `null` no OCR (RF-02/RF-03/RF-07). |
| DPI e línguas Tesseract — hardcoded ou config? | **Via `config/extracao.php`** — novos parâmetros `extracao.ocr.dpi` (`300`) e `extracao.ocr.linguas` (`['por', 'eng']`), lidos com `config()->integer(...)`/`config()->array(...)` (RF-03). |
| Directório temporário — novo disco `tmp` ou reaproveitar `storage/app/temp/` | **Reaproveitar `storage/app/temp/`** — já presente no `.gitignore` (`storage/app/temp/*` + `!.gitkeep`), sem novo disco em `config/filesystems.php`; acedido via `storage_path('app/temp/...')` directo (sem disco Laravel registado — não é um disco de ciclo de vida do `Documento`, é só um scratch space de processo). Testes usam o directório real (criado/limpo pelo próprio `ExtractorOcr`), não `Storage::fake()` (não há disco Laravel a fakear). |

## Critérios de aceitação

> Herdados da issue — nunca remover ou reformular os CAs originais sem justificação.

- [ ] CA-01: Extractor nativo devolve texto de um PDF digital de fixture; aplica correctamente o
  threshold de 50 chars. *(issue)*
- [ ] CA-02: Extractor OCR rasteriza um PDF-imagem fixture a 300 DPI e devolve texto; limpa
  `storage/app/temp/` no `finally` (verificado no teste). *(issue)*
- [ ] CA-03: Ficheiro corrompido → excepção tipada, sem deixar temporários. *(issue)*
- [ ] CA-04: Testes com fixtures reais (PDF digital, PDF-imagem gerado, ficheiro corrompido);
  **sem** chamadas a LLM. `composer test` verde (Larastan L9, type-coverage 100%, coverage 100%).
  *(issue)*
- [ ] CA-05: `system_spec` atualizado (`04-infra/external-apis.md`) + `00-index.md`. *(issue)*
- [ ] CA-06: `ExtractorOcr` liberta o `Imagick` por página (não só no fim) — verificado por teste
  com um PDF fixture de múltiplas páginas confirmando ausência de temporários intermédios
  acumulados. *(spec)*
- [ ] CA-07: Teste de asserção de substring/palavra-chave (não igualdade exacta) no texto devolvido
  pelo OCR, para não ficar flaky entre motores Tesseract de ambientes diferentes. *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/04-infra/external-apis.md` — nova secção "Extractores de texto"
  (`ExtractorTextoNativo`/`ExtractorOcr`/`ResultadoExtracao`/`FalhaExtracaoTextoException`),
  tabela `Implementado`.
- `docs/system_spec/00-index.md` — linha da tabela `Infra` (`APIs externas (IA)`) actualizada para
  reflectir os extractores implementados, ou nota adicional na mesma linha (sem ficheiro novo — a
  secção fica dentro de `external-apis.md`).
- `docs/system_spec/06-config.md` — documentar os novos parâmetros `extracao.ocr.dpi` e
  `extracao.ocr.linguas` em `config/extracao.php`.

## Verificação RGPD/NIS2

- Dados pessoais: `texto_extraido` (potencialmente PII — NIFs, nomes, valores) só existe em memória
  dentro desta camada (devolvido no `ResultadoExtracao`, nunca persistido aqui); a persistência via
  `RegistarEtapaExtracaoAction` é fora de âmbito (Issue IV). Sem logging do texto extraído — só
  metadados técnicos (nome do ficheiro, duração, nº de páginas) se vier a haver logging nesta
  camada.
- Superfície de ataque: ficheiros de entrada podem ser malformados/maliciosos (PDF corrompido,
  bomba de descompressão via `imagick`) — mitigado por `FalhaExtracaoTextoException` tipada (sem
  crash não tratado) e pelo scan de malware (`AnalisadorMalware`, `04-infra/external-apis.md`) já
  aplicado a montante no pipeline do `Documento` antes de qualquer extracção (fora de âmbito desta
  issue, mas motivo por que esta camada não repete a validação de malware). Temporários em
  `storage/app/temp/` nunca commitados (`.gitignore` já cobre) e sempre removidos após uso (RN-02).
