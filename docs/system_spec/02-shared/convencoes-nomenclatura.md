# System Spec — Shared: Convenções de Nomenclatura

> Aplicável a todo o código de domínio. Língua: Português de Portugal, excepto onde o framework impõe o nome.

---

## Língua — PT vs EN

- **Português de Portugal** em todo o código de domínio — classes, métodos, variáveis, enums, propriedades, constantes.
- **Inglês** apenas quando o framework/linguagem impõe o nome. Critério: *"o framework vai chamar isto pelo nome?"*

| Fica em inglês (framework impõe) | Exemplo |
|---|---|
| Métodos de ciclo de vida | `handle()`, `boot()`, `register()`, `store()`, `update()`, `destroy()`, `index()`, `rules()`, `messages()`, `authorize()`, `toArray()`, `definition()` |
| Sufixos de padrão estrutural | `Builder`, `Interface`, `Controller`, `Factory`, `Provider`, `Job` |
| Métodos Eloquent / Query Builder | `->where()`, `->create()`, `->find()`, `->get()` |
| Atributos PHP nativos | `#[Override]`, `#[Fillable]`, `#[Hidden]` |

---

## Métodos — VERBO + Intenção/Contexto

```php
// correcto
public function criarCategoria(CriarCategoriaDto $dados): Categoria {}
public function validarMovimento(TipoMovimento $tipo): bool {}
public function processarDocumento(string $idDocumento): void {}

// incorrecto
public function create(array $data): Categoria {}
public function validate(): bool {}
```

Excepção: métodos impostos pelo framework (ver tabela acima) — não traduzir.

---

## Variáveis e propriedades — NOME + Intenção [+ Escala]

- Entidade singular: `$categoriaDocumento`, `$idCategoria`
- Colecção: plural simples (`$categorias`, `$documentos`) — sem prefixo `lista`
- Agregados: prefixo de escala (`$totalFaturas`, `$contadorErros`, `$mediaValorDocumentos`)

```php
$categoriaDocumento = $this->repositorioCategorias->obterPorId($idCategoria);
$categorias         = $this->repositorioCategorias->listarActivas();
$totalDocumentos    = $categorias->sum('contadorDocumentos');
```

Nomes genéricos como `$data`, `$result`, `$validated`, `$campos`, `$response` são **violação** — substituir por `$dadosValidados`, `$camposParaActualizar`, etc.

---

## Enums — TitleCase PT nos cases

```php
enum TipoMovimento: string
{
    case Debito  = 'debito';
    case Credito = 'credito';
    case Neutro  = 'neutro';   // sem movimento (ex: aviso)
}
```

Cases em TitleCase PT; values backed conforme o que vai para BD / query string.

---

## Chaves primárias e estrangeiras

- **Sempre UUID** via `HasUuids` — nunca IDs incrementais.
- Colunas FK seguem o padrão `id_<entidade>` (ex: `id_categoria`, `id_documento`).

---

## snake_case vs camelCase

| Contexto | Convenção |
|---|---|
| Propriedades / variáveis / métodos PHP | camelCase PT (`$tipoMovimento`, `criarCategoria()`) |
| Colunas de base de dados | snake_case (`tipo_movimento`) — convenção Laravel/Eloquent |
| Chaves de `fill()` / array de criação | snake_case (correspondem a colunas) |
| Parâmetros de route model binding | snake_case — impostos pela rota (não renomear) |

Uma propriedade de DTO é `$tipoMovimento` mesmo que a chave do `fill()` seja `'tipo_movimento'`.
