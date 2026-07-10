# System Spec — Shared: Enums

> `app/Shared/Enums/`

Enums partilhados entre features. Todos PHP 8.5 backed enums (string). Cases em TitleCase PT per convenção CLAUDE.md.

---

## `TipoMovimento` — `App\Shared\Enums\TipoMovimento`

Classifica o tipo de movimento contabilístico de uma categoria de documento.

```php
enum TipoMovimento: string
{
    case Debito  = 'debito';
    case Credito = 'credito';
    case Neutro  = 'neutro';
}
```

- Valores na BD: `'debito'`, `'credito'`, `'neutro'` (lowercase)
- Usado em: `CategoriaDocumento::$tipo_movimento` (cast Eloquent)

---

## `DirecaoOrdenacao` — `App\Shared\Enums\DirecaoOrdenacao`

Direcção de ordenação genérica — reutilizável em todas as listagens do sistema.

```php
enum DirecaoOrdenacao: string
{
    case Asc  = 'asc';
    case Desc = 'desc';
}
```

- Valores na query string: `'asc'`, `'desc'`
- Usado em: `ListarCategoriasAction::handle()`, `ListarEntidadesAction::handle()`

---

## `EstadoDocumento` — `App\Shared\Enums\EstadoDocumento`

PHP 8.5 backed enum (string). Representa o estado de processamento de um documento. Cases em TitleCase PT; values em UPPER_SNAKE.

```php
enum EstadoDocumento: string
{
    case Pendente        = 'PENDENTE';
    case AguardaEnvio    = 'AGUARDA_ENVIO';
    case Enviado         = 'ENVIADO';
    case AguardaResposta = 'AGUARDA_RESPOSTA';
    case Processado      = 'PROCESSADO';
    case Erro            = 'ERRO';
    case Perigoso        = 'PERIGOSO';
}
```

Ciclo de estados (transições permitidas):
```
PENDENTE → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → PROCESSADO
                                                       ↘ ERRO
                                                       ↘ PERIGOSO
```

- Valores na BD: `'PENDENTE'`, `'AGUARDA_ENVIO'`, `'ENVIADO'`, `'AGUARDA_RESPOSTA'`, `'PROCESSADO'`, `'ERRO'`, `'PERIGOSO'`
- Detalhe das transições, state objects e mapeamento estado→disco em `02-shared/estados.md`
- Usado em: `Documento::$status` (cast Eloquent), `Documento::estado()` (match exaustivo)

---

## `ModoReprocessamento` — `App\Features\Documento\Reprocessar\ModoReprocessamento`

Classifica o modo de reprocessamento de um documento em `Erro`.

```php
enum ModoReprocessamento: string
{
    case Modelo     = 'MODELO';
    case Ferramenta = 'FERRAMENTA';
}
```

- Valores na BD/histórico: `'MODELO'`, `'FERRAMENTA'` (registados como `motivo` na `EtapaDocumento`)
- A semântica de fallback entre modelos/ferramentas é diferida para a issue de extracção (IA/OCR)
- Usado em: `ReprocessarDocumentoDto::$modo`, `ReprocessarDocumentoAction`, `DocumentoReprocessado`

---

## `FiltroEstadoRegisto` — `App\Shared\Enums\FiltroEstadoRegisto`

Filtro de estado para listagens de modelos com SoftDelete. Partilhado por todas
as features que usam o Padrão B de eliminação (ver `02-shared/soft-delete.md`).

```php
enum FiltroEstadoRegisto: string
{
    case Todos           = 'todos';
    case SomenteAtivos   = 'somente_ativos';
    case SomenteInativos = 'somente_inativos';
}
```

- Valor por omissão: `SomenteAtivos` (comportamento pré-SoftDelete — sem regressão de API)
- Valores na query string: `todos`, `somente_ativos`, `somente_inativos`
- Validação no `FormRequest`: `Rule::in(array_column(FiltroEstadoRegisto::cases(), 'value'))`
- Aplicado pelo scope do trait `FiltravelPorEstadoRegisto` (`filtrarPorEstadoRegisto()`) — ver `02-shared/soft-delete.md`
- Usado em: `ListarUtilizadoresAction` (#68). `ListarEntidadesAction` e `ListarCategoriasAction`: planeado (retrofit futuro)

---

## `PosicaoEmpresaMae` — `App\Shared\Enums\PosicaoEmpresaMae`

Determina, para um `TipoDocumento`, se a entidade com `e_empresa_aplicacao = true` deve aparecer como fornecedor ou cliente.

```php
enum PosicaoEmpresaMae: string
{
    case Fornecedor = 'fornecedor';
    case Cliente = 'cliente';
}
```

- Valores na BD: `'fornecedor'`, `'cliente'` (lowercase)
- Usado em: `TipoDocumento::$posicao_empresa_mae` (cast Eloquent)
- Regra de leitura pela issue futura de extracção (IA/OCR) — sem lógica de validação na camada de modelo (RN-04, `03-models/tipo-documento.md`)

---

## `CampoOrdenacaoDocumentos` — `App\Features\Documento\Listar\CampoOrdenacaoDocumentos`

Campo de ordenação da listagem de documentos.

```php
enum CampoOrdenacaoDocumentos: string
{
    case DataDocumento = 'data_documento';
    case CriadoEm     = 'created_at';
}
```

- Valores na query string: `'data_documento'`, `'created_at'`
- Usado em: `ListarDocumentosAction::handle()`, `ListarDocumentosRequest`
