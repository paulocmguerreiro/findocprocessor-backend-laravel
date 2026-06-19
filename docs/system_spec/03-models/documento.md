# System Spec — Model: Document

> `app/Models/Document.php`

_Pendente — implementado com a feature Document._

---

## Campos planeados

| Coluna | Tipo BD | Notas |
|---|---|---|
| `id` | `uuid` PK | UUIDv7 via `HasUuids` |
| `status` | `string` | Cast para `DocumentStatus` enum |
| `original_filename` | `string` | — |
| `stored_path` | `string` | — |
| `sha256_hash` | `string` | — |
| `tipo_documento` | `string` | — |
| `categoria` | `string` | FK → `categorias_documento` |
| `fornecedor` | `string` | FK → `entidades` |
| `cliente` | `string` | FK → `entidades` |
| `valor_total` | `decimal` | — |
| `data_documento` | `date` | — |
| `nif_fornecedor` | `string` | **Sensível — não logar** |
| `nif_cliente` | `string` | **Sensível — não logar** |
| `error_message` | `text\|null` | — |
| `created_at` | `timestamp` | — |
| `updated_at` | `timestamp` | — |
