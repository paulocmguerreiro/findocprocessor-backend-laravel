# Plano — Issue #45: Documento — Camada de Modelo

**Data:** 2026-06-25
**Issue:** #45
**Branch:** `feat/documento-modelo`
**Spec:** `docs/specs/2026-06-25-documento-modelo.md`

---

## Ordem de implementação

Sequência por dependências:
**T1** Enum → **T2** Migration + discos → **T3** Interface + 7 states → **T4** Policy → **T5** Model → **T6** Factory → **T7** DTOs → **T8** Resource → **T9** Testes → **T10** Verificação final.

> Após **cada** tarefa: `composer lint` + `composer refactor` antes do commit da tarefa. Checkpoint por tarefa.

---

### Tarefa 1 — Enum `EstadoDocumento`

**Ficheiro:** `app/Shared/Enums/EstadoDocumento.php`

- Criar manualmente (enum simples), conteúdo conforme Spec §2 — 7 casos PT.

**Verificação:** `composer test:types`.

---

### Tarefa 2 — Migration `create_documentos_table` + 5 discos de storage

```
php artisan make:migration create_documentos_table --no-interaction
```

- Tabela `documentos` conforme Spec §1 (UUID PK; `status` default+índice; 3 FKs `nullOnDelete`; `valor` decimal nullable; `data_documento` date+índice; `hash_sha256` único; storage NOT NULL).
- Editar `config/filesystems.php` — acrescentar 5 discos `local` PT (Spec §10).

**Verificação:** `php artisan migrate --no-interaction` sem erros; `config('filesystems.disks.entrada')` resolve.

---

### Tarefa 3 — Interface + 7 State objects

**Ficheiros:** `app/Shared/States/ContratoEstadoDocumento.php` + 7 classes (Spec §3-4).

- Interface com 4 getters comuns.
- `DocumentoPendente`, `DocumentoAguardaEnvio`, `DocumentoEnviado`, `DocumentoAguardaResposta` (parciais: + `nomeFicheiroOriginal`, `hashSha256`).
- `DocumentoErro`, `DocumentoPerigoso` (mínimos).
- `DocumentoProcessado` (completo).
- Cada uma `final readonly`, com `deDocumento(Documento $documento): self`.

> Nota: importam `App\Models\Documento` (criado em T5). A referência cruzada resolve-se em autoload; T5 fecha o ciclo. `composer test:types` só fica verde após T5 — aceitável (verificação parcial: classes existem e são sintacticamente válidas).

**Verificação:** `php -l` em cada ficheiro; verificação Larastan completa adiada para depois de T5.

---

### Tarefa 4 — Policy `DocumentoPolicy` (stub)

```
php artisan make:policy DocumentoPolicy --model=Documento --no-interaction
```

- Substituir pelos 5 métodos stub `true` (Spec §7). `final class`.

**Verificação:** `composer test:types` (a Policy referencia `Documento` — fechar com T5).

---

### Tarefa 5 — Model `Documento`

```
php artisan make:model Documento --no-interaction
```

- Substituir pelo conteúdo da Spec §5: `@property-read` completo; `#[Table]`, `#[Fillable]`, `#[UsePolicy]`; traits `HasFactory`+`HasUuids`+`RegistaActividade`; `casts()`; `atributosExcluidosDaActividade()`; `estado()` com `match` exaustivo; 3 `BelongsTo`; 5 scopes.

**Verificação:** `composer test:types` **agora verde** (fecha o ciclo states ↔ model).

---

### Tarefa 6 — Factory `DocumentoFactory`

```
php artisan make:factory DocumentoFactory --model=Documento --no-interaction
```

- `definition()` base = `processado()` (Spec §6).
- **7 states**: `pendente`, `aguardaEnvio`, `enviado`, `aguardaResposta`, `processado`, `erro`, `perigoso` — com `disco_storage` correcto e nullable nos parciais.

**Verificação:** `Documento::factory()->processado()->make()` e `->pendente()->make()` não lançam.

---

### Tarefa 7 — DTOs `CriarDocumentoManualDto` + `ActualizarDocumentoDto`

**Ficheiros:** `app/Features/Documento/Criar/CriarDocumentoManualDto.php`, `app/Features/Documento/Actualizar/ActualizarDocumentoDto.php`

- `final readonly`; construtor com invariantes (Spec §8). `valor < 0` rejeita / `0` aceita; `hashSha256` != 64 rejeita. **Sem** `fromRequest()`.

**Verificação:** `composer test:types`.

---

### Tarefa 8 — Resource `DocumentoResource`

**Ficheiro:** `app/Features/Documento/DocumentoResource.php`

- `final`, `@mixin Documento`, `toArray()` conforme Spec §9. `valor` → `(float)`; `status` → `->value`; relações via `whenLoaded()`; omite campos de storage.

**Verificação:** `composer test:types`.

---

### Tarefa 9 — Testes unitários

Criar via `php artisan make:test --pest <Nome> --no-interaction` e mover para `tests/Unit/...` conforme Spec §11:

- `tests/Unit/Models/DocumentoTest.php` — UUID; fillable; casts; 3 relações; `nullOnDelete`; 5 scopes; `estado()` para os **7** casos; audit exclui sensíveis; factory states (`disco_storage`).
- `tests/Unit/States/EstadoDocumentoStatesTest.php` — 7 state objects (getters + `estado()`).
- `tests/Unit/Policies/DocumentoPolicyTest.php` — 5 métodos `true`.
- `tests/Unit/Features/Documento/CriarDocumentoManualDtoTest.php` — happy path (`valor=0`) + cada excepção + `hash` != 64.
- `tests/Unit/Features/Documento/ActualizarDocumentoDtoTest.php` — idem.
- `tests/Unit/Features/Documento/DocumentoResourceTest.php` — campos presentes/ausentes; `valor` float; omite storage.

**Verificação:** `composer test` — 100% coverage + 100% type coverage.

---

### Tarefa 10 — Verificação final + pipeline

```bash
composer lint
composer refactor
composer test
```

- Zero erros Larastan 9; 100% coverage; 100% type coverage; Rector sem sugestões; Pint limpo.
- ArchTest verde (DTOs/states `final readonly`; enum/interface excluídos da regra "actions are final" conforme `07-testing.md`).

---

## Notas de execução

- **Ciclo de referências states ↔ Model:** T3 e T4 referenciam `Documento` antes de T5. O autoload do PHP resolve; a verificação Larastan completa só passa após T5. Não bloquear T3/T4 por isso — usar `php -l` como verificação intermédia.
- **`composer test` corre por tarefa onde fizer sentido**, mas a cobertura 100% só é atingível com os testes (T9) — as tarefas 1-8 verificam com `composer test:types` / `php artisan migrate`.
- **Nada de `fromRequest()` nos DTOs** — pertence à #57.
- **Não tocar** nas pastas scaffold EN `app/Features/Documents/*` — a slice real é `app/Features/Documento/` (PT).
