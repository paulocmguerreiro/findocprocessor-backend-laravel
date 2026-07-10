# Spec: TipoDocumento — model layer (migration + model + factory + policy + DTOs + resource + testes)

**Issue:** #84
**Brief:** docs/briefs/2026-07-09-tipo-documento-modelo.md
**Data:** 2026-07-09

## Requisitos funcionais

- RF-01: Migration cria a tabela `tipos_documento` com todas as colunas do contrato e FK `id_categoria` → `categorias_documento.id` com `restrictOnDelete()`.
- RF-02: Novo enum partilhado `App\Shared\Enums\PosicaoEmpresaMae` (`Fornecedor`/`Cliente`), documentado em `02-shared/enums.md`.
- RF-03: Model `TipoDocumento` expõe `casts()` para `posicao_empresa_mae` (→ `PosicaoEmpresaMae`) e os 4 booleans `espera_*`, com `@property-read` completo e relação `categoria(): BelongsTo` (`->withTrashed()`).
- RF-04: Factory `TipoDocumentoFactory` produz instâncias válidas no estado base (sem states adicionais), associando sempre uma `CategoriaDocumento::factory()`.
- RF-05: `TipoDocumentoPolicy` implementa `viewAny`/`view`/`create`/`update`/`delete` via `hasPermissionTo('tipos-documento.<accao>')` — sem `restore` (sem SoftDelete).
- RF-06: Migration `seed_tipos_documento_permissions` cria as 4 permissions `tipos-documento.{ver,criar,actualizar,eliminar}`; `admin` recebe todas, `utilizador` só `.ver`.
- RF-07: `CriarTipoDocumentoDto` e `ActualizarTipoDocumentoDto` (`final readonly class`) validam no construtor: `nome`/`descricao`/`idCategoria` não-vazios (trim) e pelo menos um dos 4 `espera_*` `true` — lançam `\InvalidArgumentException` caso contrário. Sem `fromRequest()`.
- RF-08: `TipoDocumentoResource` serializa todos os campos do contrato, incluindo `tipo_movimento` derivado de `$this->categoria?->tipo_movimento?->value` (nunca coluna própria) e `categoria` via `whenLoaded()` com `CategoriaDocumentoResource`.

## Requisitos não funcionais

- RNF-01: `strict_types=1` em todos os ficheiros novos.
- RNF-02: Larastan nível 9 sem erros; 100% type coverage; 100% code coverage (`composer test`).
- RNF-03: Nomenclatura de domínio em PT (métodos, variáveis, enum cases) — `02-shared/convencoes-nomenclatura.md`.
- RNF-04: `@throws` declarado nos construtores dos DTOs (`\InvalidArgumentException`) — `02-shared/padroes-tipagem.md`.

## Modelo de dados

**Tabela `tipos_documento`**

| Campo | Tipo BD | Obrigatório | Default | Notas |
|---|---|---|---|---|
| `id` | `uuid` PK | — | UUIDv7 | `HasUuids` |
| `nome` | `string(255)` | sim | — | índice único |
| `descricao` | `text` | sim | — | texto livre para a IA |
| `id_categoria` | `uuid` FK → `categorias_documento.id` | sim | — | `restrictOnDelete()` |
| `posicao_empresa_mae` | `string(50)` → `PosicaoEmpresaMae` | sim | — | cast enum |
| `espera_data_documento` | `boolean` | sim | `true` | |
| `espera_fornecedor` | `boolean` | sim | `true` | |
| `espera_cliente` | `boolean` | sim | `true` | |
| `espera_valor` | `boolean` | sim | `true` | |
| `created_at` / `updated_at` | `timestamp` | — | `timestamps()` | sem `deleted_at` |

## Regras de negócio

- RN-01: `id_categoria` é obrigatório (não nullable) — um `TipoDocumento` só existe com uma categoria definida (ao contrário de `documentos.id_categoria`, que é nullable).
- RN-02: Pelo menos um dos 4 campos `espera_*` tem de ser `true` — não pode existir uma definição sem nenhuma indicação de dados a extrair. Validado no construtor dos dois DTOs.
- RN-03: `tipo_movimento` nunca é campo próprio de `TipoDocumento` — é sempre derivado via `$tipoDocumento->categoria->tipo_movimento`, evitando duplicação/inconsistência com a categoria associada.
- RN-04: `posicao_empresa_mae` determina, para este tipo de documento, se a entidade com `e_empresa_aplicacao = true` deve aparecer como `Fornecedor` ou `Cliente` — regra de leitura pela issue futura de extracção, sem lógica de validação nesta camada.

## Dependências

- Issues bloqueantes: nenhuma.

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------- | ------- |
| nenhuma | — o Brief não levantou questões em aberto; a issue já define o contrato completo |

## Critérios de aceitação

> Herdados da issue — nunca remover ou reformular os CAs originais sem justificação.

- [ ] CA-01: Migration cria a tabela `tipos_documento` com todos os campos e `id_categoria` com `restrictOnDelete()` *(issue)*
- [ ] CA-02: Model tem casts correctos para `PosicaoEmpresaMae` e os 4 booleans *(issue)*
- [ ] CA-03: Factory produz instâncias válidas (estado base) *(issue)*
- [ ] CA-04: Policy usa `hasPermissionTo('tipos-documento.<accao>')` por método (nunca `return true`) + migration `seed_tipos_documento_permissions` (admin todas, utilizador só `.ver`) *(issue)*
- [ ] CA-05: `PolicyTest` cobre config-com-permissão (admin) vs config-sem-permissão (utilizador: escritas negadas) — matriz de 3 estados *(issue)*
- [ ] CA-06: `CriarTipoDocumentoDto` e `ActualizarTipoDocumentoDto` são `final readonly class` com construtor que valida invariantes *(issue)*
- [ ] CA-07: Construtor lança `\InvalidArgumentException` para: `nome`/`descricao`/`idCategoria` vazios (trim) **e** para os 4 `espera_*` todos `false` *(issue)*
- [ ] CA-08: `TipoDocumentoResource` serializa todos os campos do contrato com tipos correctos, incl. `tipo_movimento` derivado da categoria *(issue)*
- [ ] CA-09: Testes dos DTOs cobrem happy path + cada excepção do construtor (incl. o novo invariante cross-field) *(issue)*
- [ ] CA-10: Testes do Resource cobrem serialização (campos presentes e tipos, incl. `categoria` ausente vs presente) *(issue)*
- [ ] CA-11: 100% code coverage e 100% type coverage (`composer test`) *(issue)*
- [ ] CA-12: `tests/Unit/Models/TipoDocumentoTest.php` cobre casts (enum + booleans), relação `categoria()` e o comportamento `restrictOnDelete()` da FK *(spec)*
- [ ] CA-13: `00-index.md` actualizado com a linha do novo Model `TipoDocumento` *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/03-models/tipo-documento.md` — ficheiro novo (Model, Factory, Policy, DTOs, Resource)
- `docs/system_spec/00-index.md` — nova linha na tabela "Modelos Eloquent"
- `docs/system_spec/02-shared/enums.md` — novo enum `PosicaoEmpresaMae`
- `docs/system_spec/04-infra/autorizacao.md` — novas permissions `tipos-documento.*` (tabela de permissions + matriz role→permission)

## Verificação RGPD/NIS2

- Dados pessoais: não.
- Superfície de ataque: inalterada (sem endpoints).
