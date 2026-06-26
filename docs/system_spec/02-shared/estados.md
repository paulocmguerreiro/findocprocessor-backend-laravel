# System Spec — Shared: Estados e Contratos

> `app/Shared/States/`

---

## Ciclo de estados do `Documento`

Fluxo feliz na horizontal; ramos de falha/risco em baixo:

```
PENDENTE → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → PROCESSADO
                                                       ↘ ERRO
                                                       ↘ PERIGOSO
```

### Semântica dos estados

| Estado (enum) | Value BD | Significado |
|---|---|---|
| `Pendente` | `PENDENTE` | Documento recebido; campos de domínio podem estar a null (registo automático iniciado) |
| `AguardaEnvio` | `AGUARDA_ENVIO` | Pronto a ser enviado para o serviço de extracção |
| `Enviado` | `ENVIADO` | Enviado para o serviço de extracção (IA / OCR) |
| `AguardaResposta` | `AGUARDA_RESPOSTA` | À espera da resposta do serviço de extracção |
| `Processado` | `PROCESSADO` | Processamento concluído com sucesso; todos os campos preenchidos |
| `Erro` | `ERRO` | Falha no processamento — recuperável (permite reprocessar) |
| `Perigoso` | `PERIGOSO` | Documento marcado como potencialmente malicioso/suspeito |

### Mapeamento estado → disco de storage

| Estado | `disco_storage` |
|---|---|
| `Pendente`, `AguardaEnvio` | `entrada` |
| `Enviado`, `AguardaResposta` | `enviado` |
| `Processado` | `processado` |
| `Erro` | `erro` |
| `Perigoso` | `perigoso` |

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

**Regra:** a mudança de estado é feita na issue de Lógica (#57) via Actions. Aqui os state objects são **read-only** — sem método `correct()`.

### Grupos de campos

| Grupo | Classes | Getters específicos (além dos 4 comuns) |
|---|---|---|
| Parciais | `DocumentoPendente`, `DocumentoAguardaEnvio`, `DocumentoEnviado`, `DocumentoAguardaResposta` | `nomeFicheiroOriginal()`, `hashSha256()` |
| Mínimos | `DocumentoErro`, `DocumentoPerigoso` | — (só os 4 da interface) |
| Completo | `DocumentoProcessado` | `nomeFicheiroOriginal()`, `hashSha256()`, `idFornecedor()`, `idCliente()`, `idCategoria()`, `valor()`, `dataDocumento()` |

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

## Regras de transição (implementadas — Issue #57)

A mudança de estado é sempre feita por Actions de transição, **nunca** com `if ($doc->status == ...)`.
O mapa central é validado por `RegraTransicaoEstado` (ver `02-shared/regras-negocio.md`).

### Mapa De → Para (mapa central)

| De | Para | Action | Via |
|---|---|---|---|
| `Pendente` | `AguardaEnvio` | `MarcarAguardaEnvioDocumentoAction` | pipeline |
| `Pendente` | `Perigoso` | `MarcarPerigosoDocumentoAction` | pipeline (pré-scan) |
| `AguardaEnvio` | `Enviado` | `MarcarEnviadoDocumentoAction` | pipeline |
| `Enviado` | `AguardaResposta` | `MarcarAguardaRespostaDocumentoAction` | pipeline |
| `AguardaResposta` | `Processado` | `TransicionarProcessadoDocumentoAction` | pipeline |
| `AguardaResposta` | `Erro` | `MarcarErroDocumentoAction` | pipeline |
| `AguardaResposta` | `Perigoso` | `MarcarPerigosoDocumentoAction` | pipeline (guardrail) |
| `Erro` | `AguardaEnvio` | `ReprocessarDocumentoAction` | HTTP |
| `Processado` | `Processado` | `CorrigirDocumentoAction` | HTTP (self-loop) |

Qualquer par não listado lança `TransicaoInvalidaException` (→ 422).

Os state objects da issue #45 são read-only — sem método `correct()`. A transição, o movimento de
ficheiro entre discos e o registo em `EtapaDocumento` (issue #56) são feitos pelas Actions (#57).

---

## DTOs partilhados (`app/Shared/DTOs/`)

_Vazio até à primeira issue implementada._

DTOs de feature vivem dentro da sua slice (`app/Features/<Feature>/`). Ver `01-features/<slug>.md` para DTOs específicos de cada feature.
