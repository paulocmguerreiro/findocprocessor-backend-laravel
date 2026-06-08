# System Spec — 03: Modelos Eloquent

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## Document (app/Models/Document.php)

_Vazio até à primeira issue implementada._

Campos planeados:
- `id` (uuid)
- `status` (string — DocumentStatus enum)
- `original_filename`
- `stored_path`
- `sha256_hash`
- `tipo_documento`
- `categoria`
- `fornecedor`
- `cliente`
- `valor_total`
- `data_documento`
- `nif_fornecedor` (sensível — não logar)
- `nif_cliente` (sensível — não logar)
- `error_message`
- `created_at`
- `updated_at`
