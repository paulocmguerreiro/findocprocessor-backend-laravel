# Debrief — Issue #45: Documento — Camada de Modelo

**Data:** 2026-06-25
**Issue:** #45
**Slug:** `documento-modelo`
**Branch:** `feat/documento-modelo`
**Tipo:** feat

---

## Resumo executivo

Implementada a camada de modelo completa para o `Documento`, entidade central do domínio FinDocProcessor. Cobre migration, enum `EstadoDocumento` (7 estados PT), interface `ContratoEstadoDocumento`, 7 state objects `final readonly`, Model `Documento`, factory com 7 states, policy stub, 2 DTOs e `DocumentoResource`. Pipeline completa a verde: 399 testes, 1046 assertions, 100% coverage, 100% type coverage, Larastan 9 zero erros.

---

## O que foi implementado

| Componente | Localização | Estado |
|---|---|---|
| Migration `create_documentos_table` | `database/migrations/..._create_documentos_table.php` | ✅ |
| Enum `EstadoDocumento` (7 casos PT) | `app/Shared/Enums/EstadoDocumento.php` | ✅ |
| Interface `ContratoEstadoDocumento` | `app/Shared/States/ContratoEstadoDocumento.php` | ✅ |
| 7 state objects (`Pendente`, `AguardaEnvio`, `Enviado`, `AguardaResposta`, `Processado`, `Erro`, `Perigoso`) | `app/Shared/States/Documento*.php` | ✅ |
| Model `Documento` | `app/Models/Documento.php` | ✅ |
| Factory `DocumentoFactory` (base + 7 states) | `database/factories/DocumentoFactory.php` | ✅ |
| Policy `DocumentoPolicy` (stub) | `app/Policies/DocumentoPolicy.php` | ✅ |
| DTO `CriarDocumentoManualDto` | `app/Features/Documento/Criar/CriarDocumentoManualDto.php` | ✅ |
| DTO `ActualizarDocumentoDto` | `app/Features/Documento/Actualizar/ActualizarDocumentoDto.php` | ✅ |
| Resource `DocumentoResource` | `app/Features/Documento/DocumentoResource.php` | ✅ |
| 5 discos de storage | `config/filesystems.php` | ✅ |
| Testes unitários (model, states, policy, DTOs, resource, audit) | `tests/Unit/...` | ✅ |

---

## Decisões tomadas

### D1 — Enum e nomenclatura em PT-PT (overriding placeholders EN)
O `EstadoDocumento` substituiu o placeholder `DocumentStatus` (valores EN) existente em `02-shared/enums.md`. A decisão foi tratar a issue como autoritativa: toda a nomenclatura segue PT-PT — Model `Documento`, tabela `documentos`, slice `app/Features/Documento/`, enum `EstadoDocumento` com valores `PENDENTE`/`AGUARDA_ENVIO`/`ENVIADO`/`AGUARDA_RESPOSTA`/`PROCESSADO`/`ERRO`/`PERIGOSO`. As pastas scaffold EN `app/Features/Documents/*` (legado de scaffolding) não foram tocadas.

**Por quê:** Alinhamento com `CLAUDE.md` (código de domínio em PT-PT) e coerência com as entidades já implementadas (`Entidade`, `CategoriaDocumento`).

### D2 — `match` exaustivo sem `default` em `Documento::estado()`
O método `estado()` cobre os 7 casos do enum sem `default`. Larastan 9 valida a exaustividade: adicionar um 8.º estado ao enum forçará um erro em `test:types` imediatamente.

**Por quê:** Segurança de tipos e detecção precoce de estados não tratados.

### D3 — Interface com 4 getters comuns (não todos os campos)
`ContratoEstadoDocumento` declara apenas `estado()`, `id()`, `discoStorage()`, `nomeFicheiroStorage()` — os 4 getters comuns a todos os 7 estados. `DocumentoProcessado` expõe campos adicionais (valor, data, FKs) e os estados parciais expõem `nomeFicheiroOriginal` e `hashSha256` diretamente nas classes concretas.

**Por quê:** Forçar todos os campos na interface criaria getters inúteis com retorno `null` ou exigiria type narrowing no consumidor; deixar os extras nas classes concretas mantém o princípio do mínimo necessário na interface.

### D4 — `RegistaActividade` com exclusão de campos sensíveis
O trait `RegistaActividade` foi adicionado (decisão Checkpoint A) por consistência com `Entidade`/`CategoriaDocumento`. `atributosExcluidosDaActividade()` exclui `['hash_sha256', 'disco_storage', 'nome_ficheiro_storage']` — campos sensíveis / PII indirecta.

**Por quê:** O SHA-256 é fingerprint do ficheiro; logá-lo em claro viola o princípio de minimização de dados (RGPD).

### D5 — Cast `decimal:2` → `string` em PHP; conversão no Resource
`$documento->valor` é `string|null` (Eloquent `decimal:2` não devolve float). O `DocumentoResource` converte explicitamente: `$this->valor !== null ? (float) $this->valor : null`. O DTO `CriarDocumentoManualDto` recebe `float` como input (fronteira controlada pelo FormRequest na #57).

**Por quê:** Diferença subtil mas crítica para Larastan 9 e type-coverage 100%.

### D6 — Factory base = `processado()` com 7 states explícitos
A factory foi desenhada para cobrir os 7 ramos do `match` em `estado()`, necessário para 100% de coverage. Mapeamento estado→disco: `pendente`/`aguardaEnvio` → `entrada`; `enviado`/`aguardaResposta` → `enviado`; `processado` → `processado`; `erro` → `erro`; `perigoso` → `perigoso`.

### D7 — DTOs sem `fromRequest()`
Confirmado por `padroes-dtos.md`: `fromRequest()` pertence à issue de Lógica (#57), quando os FormRequests existirem. Os DTOs desta issue são Value Objects puros — construtor com invariantes, sem dependência HTTP.

### D8 — Sem Repository (desvio documentado)
A issue de Lógica (#57) decidirá se a listagem de documentos justifica Repository. Para esta camada de modelo não há Actions nem queries — desvio aceite e coerente com o restante domínio.

---

## O que ficou fora de âmbito

- Modelo `EtapaDocumento` (histórico/feedback de transições) — issue #56
- Actions de transição, `ListarDocumentosAction`, classes `Regra*`, Events — issue #57
- `fromRequest()` nos DTOs + DTOs de transição — issue #57
- Endpoints de API / rotas
- Repository

---

## Problemas encontrados e resoluções

**P1 — Ciclo de referências states ↔ Model**
Os state objects (`T3`) importam `App\Models\Documento` antes de `T5` criar o Model. Resolvido com `php -l` como verificação intermédia em `T3`/`T4`; Larastan completo só passa após `T5` fechar o ciclo. Não bloqueou a sequência.

**P2 — `unique()` no `hash_sha256` da factory**
`faker->unique()->sha256()` devolve uma string de 40 chars (SHA-1). Resolvido com `hash('sha256', $this->faker->unique()->sha256())` — produz 64 chars hex garantidos e únicos por teste.

**P3 — `foreignUuid()` com `nullOnDelete()`**
MySQL requer que a FK reference o tipo exacto. Confirmado com `database-schema` (MCP): `entidades.id` = `char(36)`, compatível com `foreignUuid`. SQLite ignora FKs por omissão nos testes — comportamento esperado.

---

## Aprendizagens (Vertical Slice / PHP 8.5 / Laravel 13)

### State pattern como Value Objects read-only
O padrão `final readonly class` para state objects revelou-se muito mais limpo do que seria um `switch` no Model ou métodos `isProcessado()` espalhados. O ponto crítico foi compreender que a **interface declara apenas o mínimo comum** — em vez de tentar uniformizar todos os estados, a interface contém só o que qualquer consumidor genérico precisa. O consumidor que quer campos específicos de `DocumentoProcessado` casteia explicitamente ou chama `Documento::estado()` e usa `instanceof`. Isso mantém o OCP sem hierarquias artificiais.

### `match` exaustivo como invariante de domínio verificada pelo compilador
Um `match` sem `default` sobre um BackedEnum é essencialmente uma asserção estática: "se adicionar um estado ao enum e esquecer de tratar aqui, o compilador (Larastan) avisa antes do PR". Este padrão é preferível a testes que verificam "o `default` lança excepção" — porque a garantia existe em tempo de análise, não em tempo de execução.

### Cast `decimal:2` → `string`: a fronteira string↔float é uma decisão de camada
Aprender que o Eloquent `decimal:2` devolve `string` clarificou onde cada camada é responsável pelo tipo: o Model mantém `string` (fiel ao BD), o Resource converte para `float` (contrato da API), o DTO recebe `float` (input controlado). Tentar normalizar numa só camada criaria problemas de Larastan ou perda de precisão.

### Factory com 7 states garante coverage sem mocks
Em vez de mockar o Model nos testes de `estado()`, criar 7 factory states cobre os 7 ramos do `match` com dados reais. A cobertura 100% torna-se uma consequência natural da fidelidade dos testes, não de truques de mocking.

---

## Commits desta issue

```
cc44105 feat(documento): enum EstadoDocumento — 7 estados PT
485c61f feat(documento): migration documentos + 5 discos de storage
8f56136 feat(documento): interface ContratoEstadoDocumento + 7 state objects
53291c8 feat(documento): policy DocumentoPolicy (stub)
943d95c feat(documento): Model Documento — casts, estado(), relacoes, scopes
a6f24d7 feat(documento): DocumentoFactory — base processado + 7 states
f5edfba feat(documento): DTOs CriarDocumentoManualDto + ActualizarDocumentoDto
5472412 feat(documento): DocumentoResource — serializacao JSON
8877518 test(documento): testes unitarios — model, states, policy, DTOs, resource, audit
9bff722 chore(workflow): fase documenta — Issue #45 implementada
```

---

## Próximas issues relacionadas

- **#56** — `EtapaDocumento` (histórico/feedback de transições)
- **#57** — Lógica do `Documento` (Actions, Controller, FormRequests, Events, testes Feature)
