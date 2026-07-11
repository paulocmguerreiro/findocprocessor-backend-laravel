# Spec: Documentar decisão de hard-delete do Documento no system_spec

**Issue:** #92
**Brief:** docs/briefs/2026-07-11-documentar-hard-delete-documento.md
**Data:** 2026-07-11

## Requisitos funcionais

- RF-01: `docs/system_spec/02-shared/soft-delete.md`, secção "Não usar SoftDelete", ganha duas entradas novas — `documentos` e `tipos_documento` — cada uma com o racional específico (não genérico) que justifica a exclusão.
- RF-02: `docs/system_spec/03-models/documento.md`, secção "Notas arquitecturais", ganha uma nota curta com link para `02-shared/soft-delete.md`.
- RF-03: `docs/system_spec/03-models/tipo-documento.md`, secção equivalente ("Notas arquitecturais" ou a secção de notas já existente nesse ficheiro), ganha a mesma nota curta com link para `02-shared/soft-delete.md`.

## Requisitos não funcionais

Não aplicável — issue doc-only, sem código executável, sem impacto em performance, segurança de runtime ou cobertura de testes.

## Contratos de API (se aplicável)

Não aplicável.

## Modelo de dados (se aplicável)

Não aplicável — nenhuma migration, nenhum Model alterado.

## Regras de negócio

- RN-01: A entrada de `documentos` na lista "Não usar SoftDelete" explicita que a exclusão **não** decorre do critério genérico "tabela folha sem FKs a apontar para ela" (já existente na secção) — `documentos` **é** referenciada por `etapas_documento.id_documento` (`cascadeOnDelete()`). A razão é uma decisão de negócio distinta: um documento incorrecto é eliminado directamente e re-submetido via re-upload, nunca corrigido "in place" nem precisa de ficar visível como registo inactivo; o histórico (`EtapaDocumento`) cai com ele por design (cascade, não FK protegida).
- RN-02: A entrada de `tipos_documento` reutiliza o critério genérico já existente ("tabela sem FKs a apontar para ela" — confirmado por grep a `database/migrations/*.php`, nenhuma FK referencia `tipos_documento`), mas fica registada explicitamente para não ser confundida com omissão, dado que é um modelo de domínio com Policy CRUD completa (não um registo de auditoria ou pivot, os outros exemplos já listados).
- RN-03: As notas em `03-models/documento.md` e `03-models/tipo-documento.md` não repetem o racional completo — apontam (link relativo) para `02-shared/soft-delete.md` como fonte única da decisão, evitando duas versões do mesmo texto a divergirem no futuro.

## Dependências

- Issues bloqueantes: nenhuma.

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------- | ------- |
| Âmbito exacto da nota — só `documento.md` ou também `tipo-documento.md` | Também `tipo-documento.md` — decisão do utilizador no Checkpoint A (2026-07-11): alarga ligeiramente o âmbito literal da issue #92 porque `TipoDocumento` está na mesma situação de facto (hard-delete, sem FKs), evitando deixar a mesma armadilha aberta para esse modelo. |

## Critérios de aceitação

> Herdados da issue #92 — não removidos nem reformulados.

- [ ] CA-01: `02-shared/soft-delete.md` documenta explicitamente a decisão de hard-delete para `documentos` e `tipos_documento`, na secção "Não usar SoftDelete" *(issue)*
- [ ] CA-02: `03-models/documento.md` tem nota curta a apontar para a decisão *(issue)*
- [ ] CA-03: `03-models/tipo-documento.md` tem a mesma nota curta a apontar para a decisão *(spec, RF-03 — âmbito alargado confirmado no Checkpoint A)*
- [ ] CA-04: O racional documentado distingue explicitamente `documentos` (referenciada por `etapas_documento` via cascade, decisão de negócio) de `tipos_documento` (sem nenhuma FK a apontar para ela, critério genérico já existente na secção) — não trata as duas entradas como equivalentes *(spec, RN-01/RN-02)*
- [ ] CA-05: Nenhum ficheiro de código (`app/`, `database/migrations/`, `routes/`) é alterado *(issue)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/02-shared/soft-delete.md` — secção "Não usar SoftDelete"
- `docs/system_spec/03-models/documento.md` — secção "Notas arquitecturais"
- `docs/system_spec/03-models/tipo-documento.md` — secção de notas equivalente
- `docs/system_spec/00-index.md` — sem alteração (ficheiros já indexados, nenhum ficheiro novo criado)

## Verificação RGPD/NIS2

- Dados pessoais: não aplicável — issue doc-only, sem alteração a dados ou fluxos de dados pessoais.
- Superfície de ataque: inalterada — nenhuma alteração de código, endpoint ou configuração.
