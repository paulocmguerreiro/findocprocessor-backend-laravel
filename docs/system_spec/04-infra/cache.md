# System Spec — Infra: Cache / Redis

> `app/Infrastructure/Cache/`

_Pendente — implementado quando a feature de cache for desenvolvida._

---

## Chaves planeadas

| Chave | TTL | Conteúdo |
|---|---|---|
| `documents:all` | 30s | Lista completa de documentos |
| `config:extraction_templates` | 5min | Templates de extracção activos |
| `batch:cycle_state` | dinâmico | Estado actual do ciclo batch |
