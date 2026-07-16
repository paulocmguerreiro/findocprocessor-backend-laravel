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
    case Pendente      = 'PENDENTE';
    case AnaliseMalware = 'ANALISE_MALWARE';
    case AnaliseTexto   = 'ANALISE_TEXTO';
    case AnaliseOcr     = 'ANALISE_OCR';
    case AnaliseIaLocal = 'ANALISE_IA_LOCAL';
    case AnaliseCloud   = 'ANALISE_CLOUD';
    case Processado     = 'PROCESSADO';
    case Erro           = 'ERRO';
    case Perigoso       = 'PERIGOSO';
}
```

9 estados unificados (a extracção corre localmente — o passo de análise **é** o estado, não uma
dimensão paralela). Ciclo (fluxo feliz na horizontal; `AnaliseOcr` e `AnaliseCloud` são ramos
opcionais):
```
PENDENTE → ANALISE_MALWARE → ANALISE_TEXTO → ANALISE_IA_LOCAL → PROCESSADO
                          ↘ (ANALISE_OCR)   ↗              ↘ (ANALISE_CLOUD) ↗
  qualquer análise ↘ ERRO   |   ANALISE_MALWARE/IA_LOCAL/CLOUD ↘ PERIGOSO
```

- Valores na BD: `'PENDENTE'`, `'ANALISE_MALWARE'`, `'ANALISE_TEXTO'`, `'ANALISE_OCR'`, `'ANALISE_IA_LOCAL'`, `'ANALISE_CLOUD'`, `'PROCESSADO'`, `'ERRO'`, `'PERIGOSO'`
- State objects e mapeamento estado→disco em `02-shared/estados.md`; mapa de transições em
  `01-features/documento-pipeline.md`
- Usado em: `Documento::$estado` (cast Eloquent), `Documento::estado()` (match exaustivo)

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
- Usado em: `ListarUtilizadoresAction`. `ListarEntidadesAction` e `ListarCategoriasAction`: planeado (retrofit futuro)

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

## `ResultadoEtapa` — `App\Shared\Enums\ResultadoEtapa`

PHP 8.5 backed enum (string). Resultado de um passo de IA registado por `RegistarEtapaExtracaoAction`.
Cases em TitleCase PT; values em UPPER_SNAKE.

```php
enum ResultadoEtapa: string
{
    case Sucesso = 'SUCESSO';
    case Falha   = 'FALHA';
    case EmCurso = 'EM_CURSO';
}
```

- Valores na BD: `'SUCESSO'`, `'FALHA'`, `'EM_CURSO'`
- Usado em: `EtapaDocumento::$resultado` (cast Eloquent, `null` numa linha de negócio)
- `RegistarEtapaExtracaoDto` exige `motivo` não-vazio quando `resultado === Falha`

Enums feature-specific de `Documento` (`ModoReprocessamento`, `CampoOrdenacaoDocumentos`) estão
documentados em `01-features/documento.md` ("Enums da feature"), não aqui — este ficheiro cobre
apenas enums de `app/Shared/Enums/`.
