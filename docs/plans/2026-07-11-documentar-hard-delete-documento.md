# Plano: Documentar decisão de hard-delete do Documento no system_spec

**Issue:** #92
**Spec:** docs/specs/2026-07-11-documentar-hard-delete-documento.md
**Data:** 2026-07-11

## Tarefas

### Tarefa 1 — Registar a decisão em `02-shared/soft-delete.md`

- Ficheiros a criar/alterar:
  - `docs/system_spec/02-shared/soft-delete.md` (alterar — secção "Não usar SoftDelete")
- O que implementar:
  - Na lista de "Não usar SoftDelete" (linhas 18-21 actuais), acrescentar duas entradas novas, cada uma com o racional específico (RN-01/RN-02 da Spec):
    - `documentos` — **não** pelo critério genérico "tabela folha" (é referenciada por `etapas_documento.id_documento` via `cascadeOnDelete()`); decisão de negócio: documento incorrecto é eliminado directamente e re-submetido via re-upload (`EliminarDocumentoAction` faz hard delete + apaga o ficheiro pós-commit); o histórico cai com o documento por design (cascade), não é preservado como noutros modelos.
    - `tipos_documento` — critério genérico já existente ("sem FKs a apontar para a tabela"), confirmado por grep a `database/migrations/*.php`; registado explicitamente por ser um modelo de domínio com Policy CRUD completa, não um registo de auditoria óbvio.
  - Não alterar o resto do ficheiro (Padrão B, `FiltroEstadoRegisto`, `RestaurarAction`, checklist) — fora do âmbito.
- Testes associados: nenhum (ficheiro `.md`).
- Commit: `docs(system-spec): documentar decisão de hard-delete de Documento e TipoDocumento`

### Tarefa 2 — Notas de cross-reference em `03-models/documento.md` e `03-models/tipo-documento.md`

- Ficheiros a alterar:
  - `docs/system_spec/03-models/documento.md` (secção "Notas arquitecturais")
  - `docs/system_spec/03-models/tipo-documento.md` (secção de notas equivalente — confirmar nome exacto da secção no ficheiro antes de editar)
- O que implementar:
  - Em `documento.md`, acrescentar à lista de "Notas arquitecturais" (junto de "Sem Repository", "Model não é final", etc.) uma nota curta: "**Hard-delete deliberado** — `Documento` não usa `SoftDeletes`; decisão documentada em `02-shared/soft-delete.md`."
  - Em `tipo-documento.md`, mesma nota curta adaptada, na secção de notas equivalente.
  - Nenhuma repetição do racional completo — só o link, conforme RN-03 da Spec.
- Testes associados: nenhum.
- Commit: `docs(system-spec): apontar Documento e TipoDocumento para a decisão de hard-delete`

## Ordem de implementação

1. Tarefa 1 — a fonte da verdade (`soft-delete.md`) tem de existir antes das notas que apontam para ela.
2. Tarefa 2 — depende da Tarefa 1 (link só faz sentido depois do conteúdo existir).

## Testes a escrever

Nenhum — issue doc-only, sem código executável a cobrir.

## Dependências

- Issues bloqueantes: nenhuma.

## Riscos de implementação

> Consolidados do Brief e da Spec — não apagados.

- Confundir a entrada de `documentos` com o critério genérico "tabela folha" já existente na secção — a redacção da Tarefa 1 tem de deixar explícito que `documentos` é referenciada por `etapas_documento` e mesmo assim não usa SoftDelete, por decisão de negócio (não por ausência de FKs).
- Nome exacto da secção de notas em `tipo-documento.md` pode diferir de "Notas arquitecturais" (nome usado em `documento.md`) — confirmar no ficheiro antes de editar, não assumir o mesmo cabeçalho.

## O que NÃO fazer nesta issue

- Não alterar nenhum ficheiro em `app/`, `database/migrations/` ou `routes/`.
- Não alterar `docs/system_spec/00-index.md` — nenhum ficheiro novo é criado.
- Não escrever testes — não há comportamento executável a cobrir.
- Não alterar `EliminarDocumentoAction`, `EliminarTipoDocumentoAction` ou qualquer migration.
