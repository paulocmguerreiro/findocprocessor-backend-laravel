# Debrief: Documentar decisão de hard-delete do Documento no system_spec

**Issue:** #92
**Branch:** fix/documentar-hard-delete-documento
**Data:** 2026-07-11
**Commits:** 3 commits

## O que foi implementado

Issue doc-only: registou-se explicitamente, em `02-shared/soft-delete.md`, a decisão de
**não** usar `SoftDeletes` em `Documento` e `TipoDocumento`, com o racional específico de
cada um (distinto do critério genérico "tabela folha sem FKs"). Acrescentaram-se notas de
cross-reference curtas em `03-models/documento.md` e `03-models/tipo-documento.md`, apontando
para essa fonte única da decisão.

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `docs/system_spec/02-shared/soft-delete.md` | alterado | Duas entradas novas na lista "Não usar SoftDelete": `documentos` e `tipos_documento` |
| `docs/system_spec/03-models/documento.md` | alterado | Nota curta em "Notas arquitecturais" com link para `soft-delete.md` |
| `docs/system_spec/03-models/tipo-documento.md` | alterado | Nota curta equivalente em "Notas arquitecturais" |
| `docs/briefs/2026-07-11-documentar-hard-delete-documento.md` | criado | Brief da Fase 1 |
| `docs/specs/2026-07-11-documentar-hard-delete-documento.md` | criado | Spec da Fase 1 |
| `docs/plans/2026-07-11-documentar-hard-delete-documento.md` | criado | Plano da Fase 1 |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ------------------------ | ----------- |
| `documentos` justificado por decisão de negócio distinta, não pelo critério genérico "tabela folha" | Tratar `documentos` como mais um caso do critério genérico | `documentos` **é** referenciada por `etapas_documento.id_documento` via `cascadeOnDelete()` — encaixar no critério genérico seria factualmente errado; confirmado por grep à migration `create_etapas_documento_table` |
| `tipos_documento` registado explicitamente mesmo reutilizando o critério genérico | Omitir por já estar coberto pelo critério genérico | Evita que um leitor futuro confunda ausência de entrada com omissão — é um modelo de domínio com Policy CRUD completa, não um caso óbvio de tabela auxiliar |
| Âmbito alargado a `tipo-documento.md` (não só `documento.md`, título literal da issue) | Ficar só por `documento.md` | Decisão do utilizador no Checkpoint A: `TipoDocumento` está na mesma situação de facto (hard-delete, sem FKs) — evita deixar a mesma armadilha aberta |
| Notas nos ficheiros de Model são só um link, sem repetir o racional | Duplicar o racional completo em cada ficheiro de Model | RN-03 da Spec: evita duas versões do mesmo texto a divergirem no futuro |

## Desvios ao Plano

Nenhum — as duas tarefas foram implementadas exactamente como planeado. Confirmou-se por
grep (`database/migrations/*.php`) que nenhuma FK aponta para `tipos_documento`, validando a
premissa da Tarefa 1 antes de escrever a entrada.

## Aprendizagens

A documentação de decisões de arquitectura tem um risco de armadilha que só aparece quando
se tenta escrever a excepção com precisão: `documentos` *parece* encaixar no critério genérico
"tabela folha sem FKs" da secção "Não usar SoftDelete", mas não encaixa — é referenciada por
`etapas_documento` via `cascadeOnDelete()`. Se a entrada fosse escrita apressadamente sob esse
critério genérico, ficaria uma justificação tecnicamente incorrecta no `system_spec`, que um
leitor futuro (humano ou IA) poderia usar para raciocinar mal sobre outra tabela com uma FK real
a apontar para ela. O exercício de "porque é que isto NÃO é o caso genérico" obrigou a ir à
migration confirmar o `cascadeOnDelete()` antes de escrever a frase — reforça que mesmo tarefas
doc-only beneficiam de verificação contra o código real (`grep`), não apenas contra a memória
da Spec.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/02-shared/soft-delete.md` — secção "Não usar SoftDelete" (feito)
- `docs/system_spec/03-models/documento.md` — secção "Notas arquitecturais" (feito)
- `docs/system_spec/03-models/tipo-documento.md` — secção "Notas arquitecturais" (feito)
- `docs/system_spec/00-index.md` — sem alteração (nenhum ficheiro novo criado)

## Verificação final

- [x] Linter a verde (`composer lint` / Pint — sem alterações a ficheiros `.md`)
- [x] Testes a verde (885/885, 100% coverage/types, arch, Larastan nível 9 zero erros)
- [x] Nenhum dado sensível em logs (issue doc-only, sem código)
- [x] Nenhum segredo em código (issue doc-only, sem código)
