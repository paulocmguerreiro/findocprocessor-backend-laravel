# Debrief — Issue #84: TipoDocumento — Camada de Modelo

**Data:** 2026-07-10
**Issue:** #84
**Slug:** `tipo-documento-modelo`
**Branch:** `feat/tipo-documento-modelo`

---

## Resumo

Implementada a camada de modelo de `TipoDocumento` — a entidade que define, por categoria de documento, que dados a IA deve extrair e a posição da empresa-mãe (fornecedor/cliente). Cobre enum `PosicaoEmpresaMae`, tabela `tipos_documento` (FK obrigatória `id_categoria` com `restrictOnDelete()`), permissions `tipos-documento.*`, Policy, Model com casts e relação `categoria()`, Factory, DTOs com invariante cross-field (pelo menos um `espera_*` `true`), Resource com `tipo_movimento` derivado da categoria, e testes unitários com 100% cobertura.

---

## Critérios de aceitação — resultado

| CA    | Descrição                                                                                            | Estado |
| ----- | ---------------------------------------------------------------------------------------------------- | ------ |
| CA-01 | Migration cria `tipos_documento` com `id_categoria` `restrictOnDelete()`                             | ✅     |
| CA-02 | Model tem casts correctos para `PosicaoEmpresaMae` e os 4 booleans                                   | ✅     |
| CA-03 | Factory produz instâncias válidas (estado base)                                                      | ✅     |
| CA-04 | Policy usa `hasPermissionTo` por método + migration de permissions                                   | ✅     |
| CA-05 | `PolicyTest` cobre matriz admin/utilizador                                                           | ✅     |
| CA-06 | DTOs são `final readonly class` com construtor que valida invariantes                                | ✅     |
| CA-07 | Construtor lança `InvalidArgumentException` (nome/descricao/idCategoria vazios + 4 espera\_\* false) | ✅     |
| CA-08 | Resource serializa todos os campos, incl. `tipo_movimento` derivado                                  | ✅     |
| CA-09 | Testes dos DTOs cobrem happy path + cada excepção                                                    | ✅     |
| CA-10 | Testes do Resource cobrem serialização + categoria ausente/presente                                  | ✅     |
| CA-11 | 100% code coverage e 100% type coverage                                                              | ✅     |
| CA-12 | `TipoDocumentoTest` cobre casts, relação `categoria()` e `restrictOnDelete()`                        | ✅     |
| CA-13 | `00-index.md` actualizado                                                                            | ✅     |

---

## Tarefas executadas

| #   | Tarefa                                                             | Commit    | Resultado |
| --- | ------------------------------------------------------------------ | --------- | --------- |
| T1  | Enum `PosicaoEmpresaMae`                                           | `3ff7274` | verde     |
| T2  | Migration `create_tipos_documento_table`                           | `c17dbdb` | verde     |
| T3  | Migration `seed_tipos_documento_permissions`                       | `654cf10` | verde     |
| T4  | Policy `TipoDocumentoPolicy`                                       | `56c0d03` | verde     |
| T5  | Model `TipoDocumento`                                              | `be3711c` | verde     |
| T6  | Factory `TipoDocumentoFactory`                                     | `1120311` | verde     |
| T7  | DTOs Criar/ActualizarTipoDocumentoDto                              | `ad73e4c` | verde     |
| T8  | Resource `TipoDocumentoResource`                                   | `e362566` | verde     |
| T9  | Testes unitários (Model, Policy, DTOs, Resource)                   | `1356a4e` | verde     |
| T10 | Verificação final + pipeline (`composer test` + `checkpoint:scan`) | —         | verde     |

---

## Decisões tomadas

### D1 — Ordem Model → Factory (referência temporária a classe inexistente)

**Decisão:** o Model `TipoDocumento` (T5) referencia `Database\Factories\TipoDocumentoFactory` via `@use HasFactory<TipoDocumentoFactory>` antes de a Factory existir (T6). `composer test:types` falha entre T5 e T6, ficando verde só depois de T6.

**Por quê:** mesmo padrão do precedente #45 (`Documento`), confirmado no histórico git — a ordem Model-antes-de-Factory é intencional no plano e replica a estrutura de commits já usada no projecto.

### D2 — Teste de `restrictOnDelete()` usa `forceDelete()`, não `delete()`

**Problema:** `CategoriaDocumento` usa `SoftDeletes`. Um `delete()` normal só marca `deleted_at` — não dispara a constraint de FK `restrictOnDelete()` a nível de BD.

**Decisão:** o teste em `TipoDocumentoTest.php` chama `$categoria->forceDelete()` para forçar o `DELETE` real e verificar que a `QueryException` é lançada.

**Por quê:** é a única forma de exercitar genuinamente a constraint SQL; testar com `delete()` teria passado mesmo sem a FK configurada (falso positivo).

---

## Desvios ao plano original

| Desvio                                                                                                    | Impacto                                             |
| ---------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| Nenhum desvio de fundo ao plano — apenas um ajuste de estilo de teste feito durante o checkpoint da Tarefa 9 | Nenhum impacto de âmbito; ajuste de estilo de teste |

---

## Ficheiros criados/alterados

| Ficheiro                                                                     | Operação |
| ---------------------------------------------------------------------------- | -------- |
| `app/Shared/Enums/PosicaoEmpresaMae.php`                                     | Criado   |
| `database/migrations/2026_07_10_063848_create_tipos_documento_table.php`     | Criado   |
| `database/migrations/2026_07_10_071217_seed_tipos_documento_permissions.php` | Criado   |
| `app/Policies/TipoDocumentoPolicy.php`                                       | Criado   |
| `app/Models/TipoDocumento.php`                                               | Criado   |
| `database/factories/TipoDocumentoFactory.php`                                | Criado   |
| `app/Features/TipoDocumento/Criar/CriarTipoDocumentoDto.php`                 | Criado   |
| `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoDto.php`       | Criado   |
| `app/Features/TipoDocumento/TipoDocumentoResource.php`                       | Criado   |
| `tests/Unit/Models/TipoDocumentoTest.php`                                    | Criado   |
| `tests/Unit/Policies/TipoDocumentoPolicyTest.php`                            | Criado   |
| `tests/Unit/Features/TipoDocumento/CriarTipoDocumentoDtoTest.php`            | Criado   |
| `tests/Unit/Features/TipoDocumento/ActualizarTipoDocumentoDtoTest.php`       | Criado   |
| `tests/Unit/Features/TipoDocumento/TipoDocumentoResourceTest.php`            | Criado   |

---

## Métricas finais

| Métrica           | Valor                                                             |
| ----------------- | ----------------------------------------------------------------- |
| Testes totais     | 784                                                               |
| Testes aprovados  | 784                                                               |
| Assertions        | 1804                                                              |
| Type coverage     | 100%                                                              |
| Code coverage     | 100%                                                              |
| Larastan erros    | 0                                                                 |
| Rector alterações | 0                                                                 |
| Pint alterações   | 0                                                                 |
| Checkpoint scan   | 22 passed, 0 failed, 4 warnings pré-existentes (não relacionados) |

---

## Aprendizagens

### 1. A ordem "Model antes de Factory" é um padrão consciente, não um acidente

Ao criar o Model `TipoDocumento` com `@use HasFactory<TipoDocumentoFactory>` antes de a Factory existir, `composer test:types` falha temporariamente — é esperado. O ciclo de dependências circular entre Model ↔ Policy ↔ Factory (Model referencia Policy via `#[UsePolicy]`, Policy referencia Model, Model referencia Factory) obriga a aceitar breakage temporário entre tarefas, e só fechar no fim de uma cadeia de tarefas relacionadas. Confirmar isto no histórico git de uma issue precedente (em vez de assumir que "vermelho = erro meu") evitou reordenar tarefas desnecessariamente.

### 2. `SoftDeletes` + `restrictOnDelete()` exige `forceDelete()` para testar a constraint

Uma FK `restrictOnDelete()` só actua sobre um `DELETE` SQL real. Num modelo com `SoftDeletes`, `->delete()` nunca gera esse `DELETE` — gera um `UPDATE deleted_at`. Um teste que chame `->delete()` para verificar `restrictOnDelete()` passa mesmo que a constraint esteja mal configurada (ou ausente), porque nunca chega a testar a BD. É preciso `->forceDelete()` para exercitar genuinamente o comportamento da FK.

### 3. Precedentes de teste evoluem — verificar sempre o mais recente, não o primeiro que aparece

Copiar `CategoriaDocumentoPolicyTest.php` como ponto de partida trouxe padrões desactualizados (Gate::forUser + setup manual duplicado). O padrão correcto já estava consolidado em `DocumentoPolicyTest.php` (issue mais recente) e nos helpers globais de `tests/Pest.php`. Ao replicar um padrão de teste, vale a pena verificar qual ficheiro é o mais recente da mesma categoria (Model/Policy/DTO/Resource) antes de copiar, em vez de usar o primeiro resultado de uma busca.

### 4. `whenLoaded()` vs. acesso directo à relação têm semânticas diferentes e ambas são necessárias no mesmo Resource

`TipoDocumentoResource` usa `whenLoaded('categoria')` para o campo `categoria` (omite o campo se a relação não foi eager-loaded) mas acede directamente a `$this->categoria?->tipo_movimento?->value` para o campo derivado `tipo_movimento` (dispara lazy-load se necessário). São escolhas conscientes e distintas: o primeiro existe para controlar o payload da API (evitar N+1 silencioso ao expor a relação completa); o segundo existe porque `tipo_movimento` é um valor escalar derivado que faz sentido estar sempre presente, independentemente de a relação ter sido eager-loaded explicitamente.

---

## Próximo passo

Fase 3a em curso → `/publica-implementacao #84`
