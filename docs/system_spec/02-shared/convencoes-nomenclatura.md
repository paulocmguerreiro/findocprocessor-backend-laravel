# System Spec — Shared: Convenções de Nomenclatura

> Aplicável a todo o código de domínio. Língua: Português de Portugal, excepto onde o framework impõe o nome.

---

## Língua — PT vs EN

- **Português de Portugal** em todo o código de domínio — classes, métodos, variáveis, enums, propriedades, constantes.
- **Inglês** apenas quando o framework/linguagem impõe o nome. Critério: _"o framework vai chamar isto pelo nome?"_

| Fica em inglês (framework impõe)                           | Exemplo                                                                                                                                                |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Métodos de ciclo de vida                                   | `handle()`, `boot()`, `register()`, `store()`, `update()`, `destroy()`, `index()`, `rules()`, `messages()`, `authorize()`, `toArray()`, `definition()` |
| Sufixos de padrão estrutural                               | `Builder`, `Interface`, `Controller`, `Factory`, `Provider`, `Job`                                                                                     |
| Métodos Eloquent / Query Builder                           | `->where()`, `->create()`, `->find()`, `->get()`                                                                                                       |
| Atributos PHP nativos                                      | `#[Override]`, `#[Fillable]`, `#[Hidden]`                                                                                                              |
| Propriedades/atributos de Model reconhecidos pelo Eloquent | `#[Table]`, `#[Fillable]`, `#[Casts]` (ou `$table`/`$fillable`/`$casts` sem o atributo PHP 8)                                                          |

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

## Métodos booleanos — prefixo `e`/`esta`/`validar`

Métodos que devolvem `bool` usam sempre prefixo `e`, `esta` ou `validar` — nunca a forma
Substantivo+Adjectivo (`nifValido()`, `dataValida()`). Substantivo+Adjectivo é ambíguo: não fica claro,
só pelo nome, se o método é booleano ou se devolve o próprio valor interpretado/validado.

Critério de escolha entre os três prefixos, pelo tipo de pergunta que o método responde:

| Prefixo   | Quando usar                                       | Exemplo                                                    |
| --------- | ------------------------------------------------- | ---------------------------------------------------------- |
| `e`       | Classificação/natureza da entidade — "é isto?"    | `eCliente()`, `eFornecedorEfectivo()`                      |
| `esta`    | Estado/condição transitória — "está assim agora?" | `estaConfigurado()`, `estaLimpo()`, `estaEmFalhaTecnica()` |
| `validar` | Acção de validação activa                         | `validarNif()`                                             |

```php
// correcto
public function validarNif(string $nif): bool {}
public function eDocumentoValido(Documento $documento): bool {}
public function estaConfigurado(): bool {}

public function nifValido(string $nif): bool {}

public function foiConfigurado(): bool {}     // correcto: estaConfigurado()
public function falhouTecnicamente(): bool {} // correcto: estaEmFalhaTecnica()
```

O inverso também é violação: um método que **não** devolve `bool` mas usa a forma
Substantivo+Adjectivo sugere erradamente um booleano. O nome deve reflectir a acção real (verbo +
intenção), mesmo quando o resultado parece uma validação.

```php
// correcto — interpreta e devolve DateTimeImmutable, o nome não sugere boolean
public function interpretarDataDocumento(string $dataDocumentoTexto): ?DateTimeImmutable {}

public function dataDocumentoValida(string $data): ?DateTimeImmutable {}
```

Nenhuma ferramenta automática (Pint, Rector, Larastan) detecta este tipo de violação — exige leitura
de intenção método a método.

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

**O critério é o conceito, não a língua.** As traduções PT directas destes termos são a mesma
violação: `$dados` e `$resultado` são tão genéricos quanto `$data` e `$result` — o problema nunca foi
a palavra ser inglesa, foi não dizer nada sobre o conteúdo. `$dados` → `$dadosValidados`,
`$resultado` → nome que descreva o que contém (`$categoria`, `$paginaDocumentos`, etc.), consoante o
contexto.

**Regra do escuteiro:** ao editar um ficheiro por qualquer motivo, corrigir também os identificadores
pré-existentes desse ficheiro que violem esta convenção — âmbito local (o ficheiro tocado), sem
obrigação de propagar a outros ficheiros da mesma Feature. Renomear uma slice inteira só por
consistência é refactor dedicado, não se faz incidentalmente numa tarefa.

Isto **não** se aplica a nomes impostos pelo framework — `$table`, `$fillable`, `$casts` (ou os
atributos equivalentes `#[Table]`/`#[Fillable]`) ficam em inglês porque é o Eloquent que os reconhece
pelo nome, tal como a tabela "Fica em inglês" acima já estabelece para métodos.

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

## Interfaces — sufixo `<Nome>Interface` sempre

Toda a interface do domínio leva o sufixo `<Nome>Interface` — sem excepção e sem depender de haver
ou não colisão de nome com a implementação concreta. Critério: consistência com os restantes sufixos
de padrão estrutural que já ficam em inglês (`Builder`, `Controller`, `Factory`, `Provider`, `Job` —
ver tabela "Fica em inglês" acima); um sufixo só evita ter uma categoria de classes (interfaces) a
usar prefixo enquanto todas as outras usam sufixo.

```php
// correcto
interface ClienteIAInterface { /* ... */ }
final class ClienteExtracaoIAPrism implements ClienteIAInterface { /* ... */ }

interface AnalisadorMalwareInterface { /* ... */ }
final readonly class ClamAvAnalisadorMalware implements AnalisadorMalwareInterface { /* ... */ }

// incorrecto — sem sufixo, mesmo que a implementação já tenha nome distinto
interface ClienteIA { /* ... */ }

// incorrecto — convenção anterior (prefixo Contrato), substituída nesta revisão
interface ContratoClienteIA { /* ... */ }
```

Aplica-se a qualquer interface de domínio: Repository (`<Nome>Interface` — já documentado em
`04-infra/repositories.md`), contratos de estado partilhado (`EstadoDocumentoInterface`) e
interfaces de serviços de infra (`ClienteIAInterface`, `AnalisadorMalwareInterface`).

---

## Classes abstractas — sufixo `<Nome>Abstract` sempre

Toda a classe abstracta de domínio leva o sufixo `<Nome>Abstract` — mesmo critério e mesma
uniformidade da convenção de interfaces acima. Não se aplica a classes base cujo nome é imposto por
uma convenção de framework já verificada por ArchTest — ex: `Illuminate\Routing\Controller` (tabela
"Fica em inglês" acima) e qualquer classe em `App\Console\Commands` (incl. bases abstractas), onde o
preset Laravel do Pest exige sempre o sufixo `Command` (`expect('App\Console\Commands')
->toHaveSuffix('Command')`) — acrescentar `Abstract` violaria essa regra.

```php
// correcto — nenhuma classe abstracta de domínio existe ainda no código; convenção para quando surgir
abstract class RegraValidacaoDocumentoAbstract { /* ... */ }

// incorrecto — sem sufixo
abstract class RegraValidacaoDocumento { /* ... */ }

// correcto — isento: App\Console\Commands exige sufixo Command (ArchTest), mesmo na base abstracta
abstract class EtapaExtracaoCommand extends Command { /* ... */ }
```

---

## Events — sufixo `Event` obrigatório, nome no passado

Toda a classe de Event de domínio (`app/Events/`) leva o sufixo `Event` — sem excepção — e o nome
descreve o que **já aconteceu** (particípio passado), nunca a acção a decorrer.

```php
// correcto
final class DocumentoMarcadoErroEvent { /* ... */ }
final class DocumentoProcessadoEvent { /* ... */ }

// incorrecto — sem sufixo Event
final class DocumentoMarcadoErro { /* ... */ }

// incorrecto — presente, não passado
final class DocumentoMarcaErroEvent { /* ... */ }
```

Não confundir com state objects (`app/Shared/States/`, ver `02-shared/estados.md`), que **não** levam
o sufixo `Event` — só implementam `EstadoDocumentoInterface` e podem partilhar o mesmo nome base de
um Event (ex.: `DocumentoProcessado` como state object vs `DocumentoProcessadoEvent` como Event).

---

## Chaves primárias e estrangeiras

- **Sempre UUID** via `HasUuids` — nunca IDs incrementais.
- Colunas FK seguem o padrão `id_<entidade>` (ex: `id_categoria`, `id_documento`).

---

## snake_case vs camelCase

| Contexto                               | Convenção                                                  |
| -------------------------------------- | ---------------------------------------------------------- |
| Propriedades / variáveis / métodos PHP | camelCase PT (`$tipoMovimento`, `criarCategoria()`)        |
| Colunas de base de dados               | snake_case (`tipo_movimento`) — convenção Laravel/Eloquent |
| Chaves de `fill()` / array de criação  | snake_case (correspondem a colunas)                        |
| Parâmetros de route model binding      | snake_case — impostos pela rota (não renomear)             |

Uma propriedade de DTO é `$tipoMovimento` mesmo que a chave do `fill()` seja `'tipo_movimento'`.
