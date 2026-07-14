# System Spec — Shared: Estados e Contratos

> `app/Shared/States/` — o contrato partilhado (`ContratoEstadoDocumento`) e os 7 state objects.
> O mapa de transições, o recorder de extracção e o contrato de atomicidade ficheiro↔BD vivem em
> `01-features/documento-pipeline.md` (componentes de `app/Features/Documento/`, não de `app/Shared/`).

---

## Ciclo de estados do `Documento`

Fluxo feliz na horizontal; ramos de falha/risco em baixo:

```
PENDENTE → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → PROCESSADO
    ↘ ERRO (falha do scan de malware em Pendente)        ↘ ERRO
                                                       ↘ PERIGOSO
```

### Semântica dos estados

| Estado (enum)     | Value BD           | Significado                                                                            |
| ----------------- | ------------------ | -------------------------------------------------------------------------------------- |
| `Pendente`        | `PENDENTE`         | Documento recebido; campos de domínio podem estar a null (registo automático iniciado) |
| `AguardaEnvio`    | `AGUARDA_ENVIO`    | Pronto a ser enviado para o serviço de extracção                                       |
| `Enviado`         | `ENVIADO`          | Enviado para o serviço de extracção (IA / OCR)                                         |
| `AguardaResposta` | `AGUARDA_RESPOSTA` | À espera da resposta do serviço de extracção                                           |
| `Processado`      | `PROCESSADO`       | Processamento concluído com sucesso; todos os campos preenchidos                       |
| `Erro`            | `ERRO`             | Falha no processamento — recuperável (permite reprocessar)                             |
| `Perigoso`        | `PERIGOSO`         | Documento marcado como potencialmente malicioso/suspeito                               |

### Mapeamento estado → disco de storage

| Estado                       | `disco_storage` |
| ---------------------------- | --------------- |
| `Pendente`, `AguardaEnvio`   | `entrada`       |
| `Enviado`, `AguardaResposta` | `enviado`       |
| `Processado`                 | `processado`    |
| `Erro`                       | `erro`          |
| `Perigoso`                   | `perigoso`      |

---

## Racional de design — porquê state objects (e não `if ($doc->status == ...)`)

Os state objects (`app/Shared/States/`) **não são decoração**: são o andaime deliberado do
pipeline de ingestão (OCR / análise de imagem / extracção por IA) que se pendura em cada estado.

**Intenção:** cada estado expõe **apenas** os dados e as transições **válidos nessa fase**. Ao
encapsular o comportamento por estado, restringido, por construção, operações que ainda não
existem (ou que não fazem sentido) numa dada fase — em vez de as espalhar por condicionais
`if ($doc->status == ...)` que crescem sem controlo à medida que o pipeline evolui.

**Consequências que justificam o custo:**

- **Superfície limitada por fase** — um `Documento` em `Pendente` não expõe operações de
  `Processado`; o PHPStan garante-o.
- **Extensível sem tocar no existente** — acrescentar comportamento a `Enviado` /
  `AguardaResposta` (envio ao serviço de extracção, recepção da resposta) é adicionar a esse
  state object, sem `switch` central.
- **Transições explícitas e testáves** — o mapa de transições vive num só sítio; uma
  transição inválida falha de forma previsível (`422`), não por omissão de um `if`.

---

## Interface — `ContratoEstadoDocumento`

**Ficheiro:** `app/Shared/States/ContratoEstadoDocumento.php`

```php
interface ContratoEstadoDocumento
{
    public function estado(): EstadoDocumento;
    public function id(): string;
    public function discoStorage(): string;
    public function nomeFicheiroStorage(): string;
}
```

Declara apenas os **4 getters comuns a todos os 7 estados**. Campos adicionais vivem nas classes concretas.

---

## State objects — `app/Shared/States/Documento*.php`

7 classes `final readonly`, cada uma implementando `ContratoEstadoDocumento`. Construídas via `static deDocumento(Documento $documento): self` — nunca instanciadas directamente pelo consumidor.

**Regra:** a mudança de estado é feita por Actions de transição (`01-features/documento-pipeline.md`). Aqui os state objects são **read-only** — sem método `correct()`.

### Grupos de campos

| Grupo    | Classes                                                                                      | Getters específicos (além dos 4 comuns)                                                                                  |
| -------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Parciais | `DocumentoPendente`, `DocumentoAguardaEnvio`, `DocumentoEnviado`, `DocumentoAguardaResposta` | `nomeFicheiroOriginal()`, `hashSha256()`                                                                                 |
| Mínimos  | `DocumentoErro`, `DocumentoPerigoso`                                                         | — (só os 4 da interface)                                                                                                 |
| Completo | `DocumentoProcessado`                                                                        | `nomeFicheiroOriginal()`, `hashSha256()`, `idFornecedor()`, `idCliente()`, `idCategoria()`, `valor()`, `dataDocumento()` |

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

    public function estado(): EstadoDocumento { return EstadoDocumento::Pendente; }
    public function id(): string { return $this->id; }
    public function discoStorage(): string { return $this->discoStorage; }
    public function nomeFicheiroStorage(): string { return $this->nomeFicheiroStorage; }
    public function nomeFicheiroOriginal(): string { return $this->nomeFicheiroOriginal; }
    public function hashSha256(): string { return $this->hashSha256; }
}
```

### Invocação no Model

```php
$documento->estado(); // → ContratoEstadoDocumento (match exaustivo, sem default)
```

O `match` em `Documento::estado()` cobre os 7 casos sem `default` — Larastan 9 valida a exaustividade.

---

## Onde encontrar as transições, o recorder e a atomicidade ficheiro↔BD

O mapa De→Para das transições (`RegraTransicaoEstado`), o modelo de 2 dimensões (estado de negócio ×
etapa de extracção) e o contrato de atomicidade ficheiro↔BD (`ExecutorTransicaoDocumento`,
`ReconciliarFicheirosJob`) descrevem componentes de `app/Features/Documento/`, não de
`app/Shared/States/` — documentados em `01-features/documento-pipeline.md`.

---

## DTOs partilhados (`app/Shared/DTOs/`)

_Vazio até à primeira issue implementada._

DTOs de feature vivem dentro da sua slice (`app/Features/<Feature>/`). Ver `01-features/<slug>.md` para DTOs específicos de cada feature.
