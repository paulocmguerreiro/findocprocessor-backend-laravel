# Brief — Issue #56: EtapaDocumento — Camada de Modelo

**Data:** 2026-06-25
**Issue:** #56
**Slug:** `etapa-documento-modelo`
**Branch:** `feat/etapa-documento-modelo`
**Tipo:** feat
**Labels:** prio:p2, scope:domain, stack:laravel, type:feat

---

## Contexto

`EtapaDocumento` é a **linha temporal de domínio** do documento: o histórico tipado das etapas
por que o documento passou (registo, envio, processamento, erro, reprocessamento…). É uma
**feature para o utilizador** — distinta do audit trail técnico (`spatie/laravel-activitylog`,
já mergeado), que é compliance/auditoria genérica. Esta é o ciclo de vida do documento, exposto
na API.

A tabela é **append-only / imutável**: cada transição de estado cria uma linha nova; nunca há
updates. As Actions de transição (issue de Lógica) gravarão cada linha **dentro da mesma
`DB::transaction()`** da mudança de estado — histórico e estado nunca divergem.

> **Âmbito desta issue: model layer pura** de `EtapaDocumento`. A escrita das linhas e o
> endpoint/Resource de leitura ficam na issue de Lógica (fora de âmbito).

Depende de `Documento` e `EstadoDocumento` (issue #45, já mergeada).

---

## O que vai ser construído

| Componente | Localização | Descrição |
|---|---|---|
| Migration | `database/migrations/..._create_etapas_documento_table.php` | Tabela `etapas_documento`: UUID PK, FKs, índices, **sem `updated_at`** |
| Model | `app/Models/EtapaDocumento.php` | HasUuids, cast `EstadoDocumento`, `const UPDATED_AT = null`, 2 relações |
| Relação | `app/Models/Documento.php` | `historico` (`hasMany EtapaDocumento`, ordenada por `created_at` asc) |
| Factory | `database/factories/EtapaDocumentoFactory.php` | base `Pendente` + states `processado/erro/perigoso/manual` |
| Testes | `tests/Unit/Models/EtapaDocumentoTest.php` | model, casts, relações, `cascadeOnDelete`/`nullOnDelete`, factory states |
| Testes | `tests/Unit/Models/DocumentoTest.php` (adição) | relação `historico` ordenada |

---

## Modelo de dados — tabela `etapas_documento`

| Coluna | Tipo BD | Nullable | Índice | Notas |
|---|---|---|---|---|
| `id` | `uuid` PK | Não | PK | UUIDv7 via `HasUuids` |
| `id_documento` | `uuid` FK | Não | FK | → `documentos`; **`cascadeOnDelete()`** (histórico segue o documento) |
| `estado` | `string(50)` | Não | simples | cast → `EstadoDocumento`; a etapa atingida |
| `motivo` | `text` | Sim (`null`) | — | motivo/resposta/nota; pode conter detalhe sensível |
| `id_utilizador` | **ver Decisão #1** | Sim (`null`) | FK | → `users`; `null` = passo automático; `nullOnDelete()` |
| `created_at` | `timestamp` | — | — | data+hora da etapa; **sem `updated_at`** |

---

## Decisões técnicas relevantes

1. **⚠️ FK `id_utilizador` — desvio à issue (DECISÃO PARA CHECKPOINT A).**
   A issue descreve `id_utilizador` como `uuid` FK → `utilizadores`. Verificado contra o código
   e o schema real (`database-schema`): **a tabela é `users`, `users.id` é `bigint unsigned
   auto_increment`, o Model `User` tem `@property-read int $id` e não usa `HasUuids`. Não existe
   model `Utilizador` nem tabela `utilizadores`.**
   → Um `foreignUuid('id_utilizador')->constrained('utilizadores')` **falharia** (tabela
   inexistente + incompatibilidade de tipo bigint↔uuid).
   **Recomendação:** seguir o schema real — `foreignId('id_utilizador')->nullable()
   ->constrained('users')->nullOnDelete()`, relação `utilizador()` → `belongsTo(User::class,
   'id_utilizador')`, `@property-read ?int $id_utilizador`. Documentar o desvio.
   (Alternativa rejeitada: migrar `users` para UUID — fora de âmbito desta issue de modelo e
   com impacto transversal em auth/Sanctum/Spatie.)

2. **Append-only — NÃO usar `RegistaActividade`.** Esta tabela *é* o histórico de domínio;
   aplicar-lhe o audit trail técnico (`activitylog`) seria registo duplicado. O Model **não**
   usa o trait `RegistaActividade` (ao contrário de `Documento`/`Entidade`/`CategoriaDocumento`).

3. **Sem `updated_at`.** Migration: declarar só `created_at` (não usar `$table->timestamps()`).
   Model: `const UPDATED_AT = null` (CA-02) — Eloquent passa a gerir apenas `created_at`.
   `usesTimestamps()` continua `true` (created_at é gerido).

4. **`cascadeOnDelete()` em `id_documento`** (CA-01) — distinto do `nullOnDelete()` usado nas FKs
   de `documentos`: o histórico não faz sentido sem o documento, logo é eliminado em cascata.

5. **`@property-read` completo** (convenção obrigatória): `id`, `id_documento`, `estado`
   (`EstadoDocumento`), `motivo` (`?string`), `id_utilizador` (`?int` — ver Decisão #1),
   `created_at` (`Carbon`), `documento` (`Documento`), `utilizador` (`?User`).

6. **Cast** `estado => EstadoDocumento::class` (CA-03). Sem cast de `valor`/`data` — não existem.

7. **Relação `Documento->historico`** (CA-04): `hasMany(EtapaDocumento::class, 'id_documento')
   ->orderBy('created_at')` (ascendente). Primeira relação `hasMany` do codebase — sem precedente
   interno; segue a convenção de FK `id_<entidade>`.

8. **Model não-`final`** — coerente com os outros Models (`Documento`, `Entidade`); o ArchTest
   "actions are final" não cobre Models.

9. **`#[Table('etapas_documento')]` / `#[Fillable([...])]` como atributos PHP** — nunca
   propriedades. Fillable: `['id_documento', 'estado', 'motivo', 'id_utilizador']`.

10. **Factory** (CA-06): base = `estado Pendente`, `id_documento` via `Documento::factory()`,
    `id_utilizador = null`, `motivo = null`. States: `processado()`, `erro()` (com `motivo`),
    `perigoso()` (com `motivo`), `manual()` (`estado Processado` + `id_utilizador` via
    `User::factory()`).

11. **SQLite (testes) vs MySQL (prod)** — paridade já validada (#45). FKs com cascade/nullOnDelete
    funcionam em ambos com `foreign_keys` ON.

---

## Critérios de aceitação (da issue)

- CA-01 — Migration cria `etapas_documento` com campos/índices/FKs; `id_documento` `cascadeOnDelete()`
- CA-02 — Sem `updated_at`; Model `const UPDATED_AT = null`
- CA-03 — `estado` faz cast para `EstadoDocumento`
- CA-04 — `Documento->historico` devolve etapas ordenadas por `created_at` asc
- CA-05 — `id_utilizador` nullable (passo automático) com `nullOnDelete()`
- CA-06 — Factory produz instâncias válidas para cada state
- CA-07 — `composer test`: 100% coverage + 100% type coverage; zero erros Larastan

---

## Riscos identificados

- **R1 (alto) — FK `id_utilizador`:** conflito issue (uuid/`utilizadores`) vs schema real
  (bigint/`users`). Resolvido pela Decisão #1; **requer aprovação no Checkpoint A**.
- **R2 (médio) — duplicação de auditoria:** se `RegistaActividade` fosse aplicado, cada etapa
  seria também registada no `activitylog`. Mitigado pela Decisão #2 (não usar o trait).
- **R3 (baixo) — `motivo` sensível:** pode conter detalhe (injecção detectada, erro). Não é
  logado em claro; não é exposto nesta camada (Resource fica na issue de Lógica). CA de não-log
  reavaliado na issue de Lógica.
- **R4 (baixo) — `const UPDATED_AT = null` + Larastan 9:** confirmar que a tipagem do override
  passa o nível 9. Mitigação: seguir exactamente a assinatura do framework (`?string`).

## Questões em aberto

- **Q1:** Confirmar a resolução da Decisão #1 (FK `id_utilizador` → `bigint`/`users`). — Checkpoint A.
- **Q2:** O nome da relação é `historico` (singular, sem acento por ser identificador) conforme a
  issue — confirmado, mantém-se.
