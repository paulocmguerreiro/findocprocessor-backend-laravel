# Debrief — Issue #56: EtapaDocumento — Camada de Modelo

**Data:** 2026-06-26
**Issue:** #56
**Slug:** `etapa-documento-modelo`
**Branch:** `feat/etapa-documento-modelo`

---

## Resumo

Implementada a camada de modelo de `EtapaDocumento` — a linha temporal de domínio do documento. Cobre tabela `etapas_documento` (append-only, sem `updated_at`), Model com cast `EstadoDocumento`, relação `Documento->historico`, `EtapaDocumentoFactory` com 4 states, e testes unitários com 100% cobertura.

---

## Critérios de aceitação — resultado

| CA | Descrição | Estado |
|---|---|---|
| CA-01 | Migration cria `etapas_documento`; `id_documento` `cascadeOnDelete()` | ✅ |
| CA-02 | Sem `updated_at`; Model `const UPDATED_AT = null` | ✅ |
| CA-03 | `estado` faz cast para `EstadoDocumento` | ✅ |
| CA-04 | `Documento->historico` devolve etapas ordenadas por `created_at` asc | ✅ |
| CA-05 | `id_utilizador` nullable; `nullOnDelete()` | ✅ |
| CA-06 | Factory produz instâncias válidas para cada state | ✅ |
| CA-07 | `composer test`: 100% coverage + 100% type coverage + Larastan 9 zero erros | ✅ |

---

## Tarefas executadas

| # | Tarefa | Commits | Resultado |
|---|---|---|---|
| T1 | Migration `etapas_documento` | `eeff1be` | verde |
| T2 | Model `EtapaDocumento` | `317b9d6` | verde |
| T3 | Relação `Documento->historico` | `874be50` | verde |
| T4 | Factory `EtapaDocumentoFactory` | `6f5cacc` | verde |
| T5 | Testes (EtapaDocumentoTest + DocumentoTest) | `d995cfb` | verde |
| — | Workflow state Fase 2→documenta | `d13b58c` | — |

---

## Decisões tomadas

### D1 — FK `id_utilizador`: bigint → `users` (não uuid/`utilizadores`)

**Problema:** A issue descrevia `id_utilizador` como UUID FK → `utilizadores`. O schema real é `users.id` bigint auto_increment sem `HasUuids`.

**Decisão:** `foreignId('id_utilizador')->nullable()->constrained('users')->nullOnDelete()`. Relação `utilizador()` → `BelongsTo(User::class, 'id_utilizador')`. `@property-read ?int $id_utilizador`.

**Por quê:** Um `foreignUuid` sobre `users.id bigint` falharia em runtime. Migrar `users` para UUID estaria fora de âmbito e teria impacto transversal (Sanctum, Spatie/Permission, audit trail).

**Alternativa rejeitada:** Migrar `users` para UUID — fora de âmbito desta issue de modelo, impacto transversal em auth.

### D2 — Sem `RegistaActividade`

**Decisão:** O trait `RegistaActividade` **não foi aplicado** ao `EtapaDocumento`.

**Por quê:** Esta tabela *é* o histórico de domínio. Aplicar-lhe o audit trail técnico (spatie/activitylog) seria registo duplicado — cada etapa apareceria também em `activity_log`.

### D3 — Model não-`final`

**Decisão:** `EtapaDocumento` não é `final`.

**Por quê:** Coerente com os restantes Models (`Documento`, `Entidade`, `CategoriaDocumento`). O ArchTest "actions are final" não cobre Models.

### D4 — `const UPDATED_AT = null` + Larastan 9

**Decisão:** `public const UPDATED_AT = null;` (constante, não método).

**Por quê:** Larastan 9 aceita esta forma — é assinatura do framework Eloquent. A constante `?string` segue a declaração em `Model::UPDATED_AT`. `usesTimestamps()` mantém-se `true` (só `created_at` é gerido).

---

## Desvios ao plano original

| Desvio | Impacto |
|---|---|
| `id_utilizador` bigint→`users` em vez de uuid→`utilizadores` (Decisão D1) | Schema correcto desde o início; nenhum impacto futuro |
| `EtapaDocumento` sem `RegistaActividade` (Decisão D2) | Redução de duplicação; comportamento mais limpo |

---

## Ficheiros criados/alterados

| Ficheiro | Operação |
|---|---|
| `database/migrations/2026_06_26_100641_create_etapas_documento_table.php` | Criado |
| `app/Models/EtapaDocumento.php` | Criado |
| `app/Models/Documento.php` | Alterado (relação `historico` + PHPDoc) |
| `database/factories/EtapaDocumentoFactory.php` | Criado |
| `tests/Unit/Models/EtapaDocumentoTest.php` | Criado |
| `tests/Unit/Models/DocumentoTest.php` | Alterado (bloco `historico`) |

---

## Métricas finais

| Métrica | Valor |
|---|---|
| Testes totais | 420 |
| Testes aprovados | 420 |
| Assertions | 1077 |
| Type coverage | 100% |
| Code coverage | 100% |
| Larastan erros | 0 |
| Rector alterações | 0 |
| Pint alterações | 0 |

---

## Aprendizagens

### 1. `hasMany` com FK explícita (`id_<entidade>`) é intencional e óbvio em context PT

O codebase usa `foreignUuid('id_documento')` em vez do Eloquent default `documento_id`. Isso exige passar a FK explicitamente: `hasMany(EtapaDocumento::class, 'id_documento')`. É a primeira relação `hasMany` do codebase — todas as anteriores eram `belongsTo`. A convenção de nomenclatura PT (`id_<entidade>`) é consistente e não cria ambiguidade; apenas requer que sempre se passe a FK ao definir relações Eloquent.

### 2. Append-only em Eloquent: `const UPDATED_AT = null` é suficiente e limpo

Para uma tabela sem `updated_at` (append-only), o idioma correcto é `public const UPDATED_AT = null;` no Model. Não é necessário sobrepor `usesTimestamps()`, nem remover o trait de timestamps. O Eloquent gere `created_at` normalmente e ignora `updated_at`. A alternativa (coluna `timestamp('created_at')` na migration + `const` no Model) passou Larastan 9 sem ajustes — a constante é do tipo `string|null` e segue a assinatura do framework.

### 3. Separação auditoria de domínio vs. auditoria técnica

`EtapaDocumento` *é* o audit trail do domínio — é a feature para o utilizador final (histórico de estados). `RegistaActividade` é o audit trail técnico (compliance/logs internos). Aplicar os dois ao mesmo recurso seria ruído: cada transição apareceria em dois lugares com fins distintos. A regra é: se a tabela *é* o log, não se loga o log.

### 4. Desvio de schema detectado antes da implementação (Checkpoint A) previne erros de runtime

A análise com `database-schema` no planeamento revelou que `users.id` é bigint e não UUID. Sem esta verificação, o `foreignUuid('id_utilizador')->constrained('utilizadores')` teria falhado em runtime (tabela inexistente + tipo errado). O padrão de verificar o schema real antes de escrever migrations é crítico e deve preceder qualquer FK que aponte para modelos "externos" ao domínio (como `User` — gerido pelo framework/Sanctum).

---

## Próximo passo

Fase 3a concluída → `/publica-implementacao #56`
