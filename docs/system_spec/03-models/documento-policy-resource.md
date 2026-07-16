# System Spec — Model: Documento (Policy, DTOs, Resource)

> `app/Policies/DocumentoPolicy.php` + `app/Features/Documento/DocumentoResource.php`. Extraído de
> `03-models/documento.md` (WRN-033) por limiar de tamanho (~200 linhas) — migration/Model/Factory/
> relações/scopes continuam nesse ficheiro.

---

## Policy `DocumentoPolicy`

**Ficheiro:** `app/Policies/DocumentoPolicy.php`

Autorização granular via `hasPermissionTo(...)` — `viewAny`/`view` exigem `documentos.ver`; `create` exige `documentos.criar`; `update` exige `documentos.actualizar`; `delete` exige `documentos.eliminar`. As permissões e a matriz role→permission estão em `04-infra/autorizacao.md`.

```php
final class DocumentoPolicy
{
    public function viewAny(User $utilizador): bool  { return $utilizador->hasPermissionTo('documentos.ver'); }
    public function view(User $utilizador, Documento $documento): bool  { return $utilizador->hasPermissionTo('documentos.ver'); }
    public function create(User $utilizador): bool  { return $utilizador->hasPermissionTo('documentos.criar'); }
    public function update(User $utilizador, Documento $documento): bool  { return $utilizador->hasPermissionTo('documentos.actualizar'); }
    public function delete(User $utilizador, Documento $documento): bool  { return $utilizador->hasPermissionTo('documentos.eliminar'); }
}
```

---

## DTOs

Os DTOs do Documento pertencem à camada de lógica e estão documentados — em forma tabular — em `01-features/documento.md` (secção DTOs). Os DTOs originais (`CriarDocumentoManualDto`, `ActualizarDocumentoDto`) foram **substituídos** por `RegistarDocumentoManualDto` e `CorrigirDocumentoDto` — os originais incluíam campos de storage que não devem vir do cliente.

---

## Resource `DocumentoResource`

**Ficheiro:** `app/Features/Documento/DocumentoResource.php`

```json
{
  "id": "uuid",
  "estado": "PROCESSADO",
  "id_responsavel": 1,
  "fornecedor": { ... },
  "cliente": { ... },
  "categoria": { ... },
  "valor": 1234.56,
  "data_documento": "2026-06-25",
  "nome_ficheiro_original": "fatura.pdf",
  "hash_sha256": "abc123...64chars",
  "criado_em": "2026-06-25T10:00:00.000000Z",
  "actualizado_em": "2026-06-25T10:00:00.000000Z"
}
```

- `estado` → `->value` (string UPPER_SNAKE) — o progresso de extracção lê-se aqui (máquina unificada)
- `id_responsavel` → `int|null` (id do utilizador autor; não expõe nome/email — só o id)
- `valor` → conversão explícita `(float)` (cast `decimal:2` devolve `string`)
- Relações via `whenLoaded()` + `EntidadeResource` / `CategoriaDocumentoResource`
- **Não expõe** `disco_storage` nem `nome_ficheiro_storage` (detalhes internos / PII indirecta)
- **Sem `etapa_extracao`** — a coluna deixou de existir (#110); nunca expôs, nem expõe,
  `texto_extraido`/`dados_json` (PII).
