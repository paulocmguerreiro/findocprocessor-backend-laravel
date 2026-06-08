# System Spec — 05: Rotas API

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## routes/api.php (planeado)

| Método | Path                               | Controller/Action            | Estado   |
| ------ | ---------------------------------- | ---------------------------- | -------- |
| GET    | `/state`                           | DocumentController           | pendente |
| GET    | `/events`                          | SseController                | pendente |
| GET    | `/config`                          | ConfigController             | pendente |
| GET    | `/files/list`                      | FileController               | pendente |
| POST   | `/files/open`                      | FileController               | pendente |
| POST   | `/upload`                          | UploadController             | pendente |
| POST   | `/documents/manual`                | DocumentController           | pendente |
| POST   | `/action/correct`                  | DocumentController           | pendente |
| POST   | `/action/delete-error`             | DocumentController           | pendente |
| POST   | `/action/delete-done`              | DocumentController           | pendente |
| POST   | `/action/reset-error`              | DocumentController           | pendente |
| POST   | `/action/force-cycle`              | BatchController              | pendente |
| GET    | `/config/extraction-templates`     | ConfigController             | pendente |
| POST   | `/config/extraction-templates`     | ConfigController             | pendente |
| PUT    | `/config/extraction-templates/{id}`| ConfigController             | pendente |
