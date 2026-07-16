# System Spec — Shared: Estados e Contratos

> `app/Shared/States/` — o contrato partilhado (`ContratoEstadoDocumento`) e os 9 state objects.
> O mapa de transições, o recorder de extracção e o contrato de atomicidade ficheiro↔BD vivem em
> `01-features/documento-pipeline.md` (componentes de `app/Features/Documento/`, não de `app/Shared/`).

---

## Ciclo de estados do `Documento`

Máquina de estados **unificada**: a extracção corre localmente, por isso cada passo de análise é um
estado próprio de `EstadoDocumento` (não há dimensão de extracção paralela). Fluxo feliz na
horizontal; `AnaliseOcr`/`AnaliseCloud` são ramos opcionais; `Erro`/`Perigoso` em baixo:

```
PENDENTE → ANALISE_MALWARE → ANALISE_TEXTO → ANALISE_IA_LOCAL → PROCESSADO
                          ↘ (ANALISE_OCR) ↗                ↘ (ANALISE_CLOUD) ↗
   qualquer análise que falha ↘ ERRO  ·  malware/IA/cloud suspeitos ↘ PERIGOSO
   ERRO → PENDENTE (reprocessamento)  ·  PROCESSADO → PROCESSADO (correcção)
```

### Semântica dos estados

| Estado (enum)     | Value BD           | Significado                                                                            |
| ----------------- | ------------------ | -------------------------------------------------------------------------------------- |
| `Pendente`        | `PENDENTE`         | Documento recebido; campos de domínio podem estar a null (registo automático iniciado) |
| `AnaliseMalware`  | `ANALISE_MALWARE`  | Em análise de malware (scan ClamAV)                                                     |
| `AnaliseTexto`    | `ANALISE_TEXTO`    | Em extracção de texto nativa (pdfparser)                                                |
| `AnaliseOcr`      | `ANALISE_OCR`      | Em OCR (ramo opcional, quando o texto nativo é insuficiente)                            |
| `AnaliseIaLocal`  | `ANALISE_IA_LOCAL` | Em extracção por IA local                                                               |
| `AnaliseCloud`    | `ANALISE_CLOUD`    | Em extracção por IA cloud (ramo opcional, quando a IA local não basta)                  |
| `Processado`      | `PROCESSADO`       | Processamento concluído com sucesso; todos os campos preenchidos                       |
| `Erro`            | `ERRO`             | Falha no processamento — recuperável (permite reprocessar)                             |
| `Perigoso`        | `PERIGOSO`         | Documento marcado como potencialmente malicioso/suspeito                               |

### Mapeamento estado → disco de storage

| Estado                                                | `disco_storage` |
| ----------------------------------------------------- | --------------- |
| `Pendente`, `AnaliseMalware`, `AnaliseTexto`, `AnaliseOcr` | `entrada`  |
| `AnaliseIaLocal`, `AnaliseCloud`                      | `enviado`       |
| `Processado`                                          | `processado`    |
| `Erro`                                                | `erro`          |
| `Perigoso`                                            | `perigoso`      |

---

## Racional de design — porquê state objects (e não `if ($doc->estado == ...)`)

Os state objects (`app/Shared/States/`) **não são decoração**: são o andaime deliberado do
pipeline de ingestão (OCR / análise de imagem / extracção por IA) que se pendura em cada estado.

**Intenção:** cada estado expõe **apenas** os dados e as transições **válidos nessa fase**. Ao
encapsular o comportamento por estado, restringido, por construção, operações que ainda não
existem (ou que não fazem sentido) numa dada fase — em vez de as espalhar por condicionais
`if ($doc->estado == ...)` que crescem sem controlo à medida que o pipeline evolui.

**Consequências que justificam o custo:**

- **Superfície limitada por fase** — um `Documento` em `Pendente` não expõe operações de
  `Processado`; o PHPStan garante-o.
- **Extensível sem tocar no existente** — acrescentar comportamento a um passo de análise
  (`AnaliseTexto`, `AnaliseIaLocal`, …) é adicionar a esse state object, sem `switch` central.
- **Transições explícitas e testáves** — o mapa de transições vive num só sítio; uma
  transição inválida falha de forma previsível (`422`), não por omissão de um `if`.

---

## Interface — `ContratoEstadoDocumento`

**Ficheiro:** `app/Shared/States/ContratoEstadoDocumento.php`

```php
interface ContratoEstadoDocumento
{
    public function obterEstado(): EstadoDocumento;
    public function obterId(): string;
    public function obterDiscoStorage(): string;
    public function obterNomeFicheiroStorage(): string;
}
```

Declara apenas os **4 getters comuns a todos os 9 estados**. Campos adicionais vivem nas classes concretas.

Prefixo `obter` (não a forma nua `estado()`/`id()`) — convenção VERBO+Intenção de
`convencoes-nomenclatura.md` aplica-se também a acessores de leitura pura; evita ainda colisão de
leitura com `Documento::estado()` (método do Model, devolve este objecto — nome diferente do getter
do próprio objecto `estado()` → `obterEstado()`).

---

## State objects — `app/Shared/States/Documento*.php`

9 classes `final readonly`, cada uma implementando `ContratoEstadoDocumento`. Construídas via `static deDocumento(Documento $documento): self` — nunca instanciadas directamente pelo consumidor.

**Regra:** a mudança de estado é feita por Actions de transição (`01-features/documento-pipeline.md`). Aqui os state objects são **read-only** — sem método `correct()`.

### Grupos de campos

| Grupo    | Classes                                                                                      | Getters específicos (além dos 4 comuns)                                                                                  |
| -------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Parciais | `DocumentoPendente`, `DocumentoAnaliseMalware`, `DocumentoAnaliseTexto`, `DocumentoAnaliseOcr`, `DocumentoAnaliseIaLocal`, `DocumentoAnaliseCloud` | `obterNomeFicheiroOriginal()`, `obterHashSha256()`                          |
| Mínimos  | `DocumentoErro`, `DocumentoPerigoso`                                                         | — (só os 4 da interface)                                                                                                 |
| Completo | `DocumentoProcessado`                                                                        | `obterNomeFicheiroOriginal()`, `obterHashSha256()`, `obterIdFornecedor()`, `obterIdCliente()`, `obterIdCategoria()`, `obterValor()`, `obterDataDocumento()` |

### Padrão de implementação (exemplo `DocumentoPendente`)

```php
final readonly class DocumentoPendente implements ContratoEstadoDocumento
{
    public function __construct(
        private string $id,
        private string $discoStorage,
        private string $nomeFicheiroStorage,
        private string $nomeFicheiroOriginal,
        private string $hashSha256,
    ) {}

    public static function deDocumento(Documento $documento): self
    {
        return new self(
            id: $documento->id,
            discoStorage: $documento->disco_storage,
            nomeFicheiroStorage: $documento->nome_ficheiro_storage,
            nomeFicheiroOriginal: $documento->nome_ficheiro_original,
            hashSha256: $documento->hash_sha256,
        );
    }

    public function obterEstado(): EstadoDocumento { return EstadoDocumento::Pendente; }
    public function obterId(): string { return $this->id; }
    public function obterDiscoStorage(): string { return $this->discoStorage; }
    public function obterNomeFicheiroStorage(): string { return $this->nomeFicheiroStorage; }
    public function obterNomeFicheiroOriginal(): string { return $this->nomeFicheiroOriginal; }
    public function obterHashSha256(): string { return $this->hashSha256; }
}
```

### Invocação no Model

```php
$documento->estado(); // → ContratoEstadoDocumento (match exaustivo, sem default)
```

O `match` em `Documento::estado()` cobre os 9 casos sem `default` — Larastan 9 valida a exaustividade.

---

## Onde encontrar as transições, o recorder e a atomicidade ficheiro↔BD

O mapa De→Para das transições (`RegraTransicaoEstado`) e o recorder de extracção
(`RegistarEtapaExtracaoAction`) descrevem componentes de `app/Features/Documento/`, não de
`app/Shared/States/` — documentados em `01-features/documento-pipeline.md`. O contrato de
atomicidade ficheiro↔BD (`ExecutorTransicaoDocumento`, `ReconciliarFicheirosJob`) está em
`01-features/documento-reconciliacao.md`.

---

## DTOs partilhados (`app/Shared/DTOs/`)

_Vazio até à primeira issue implementada._

DTOs de feature vivem dentro da sua slice (`app/Features/<Feature>/`). Ver `01-features/<slug>.md` para DTOs específicos de cada feature.
