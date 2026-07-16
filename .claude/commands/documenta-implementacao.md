---
description: Fase 3a — Debrief + SYSTEM_SPEC + Changelog (artefactos locais)
allowed-tools: [Bash, Read, Write, Edit]
model: sonnet
effort: high
---

# /documenta-implementacao

**Fase 3a** — Documenta a implementação: Debrief → SYSTEM_SPEC → Changelog → README.
Produz apenas artefactos locais. Para criar o PR, usar `/publica-implementacao`.

## Argumentos
- `$ARGUMENTS`: número da issue (ex: `#5`) — opcional; se omitido, lê de `workflow-state.md`

## Pré-condições
1. Ler `docs/workflow-state.md` — confirmar `fase: documenta`
2. Testes a verde (verificar antes de continuar)

## Passos

1. Skill `escreve-debrief` → `docs/debriefs/YYYY-MM-DD-<slug>.md`
2. Skill `pausa-checkpoint` tipo=D — mostrar secção "Decisões tomadas" e aguardar confirmação
3. Skill `actualiza-spec` — actualizar `docs/system_spec/*.md` conforme `SYSTEM_SPEC_MAP` do `CLAUDE.md`.
   Esta é a **única** oportunidade de actualizar o system_spec (a Fase 2 não o faz — ver
   `escreve-plan.md`) — correr sempre a checklist de verificação da própria skill antes de avançar
   para o passo seguinte.
4. Skill `actualiza-changelog` — adicionar entrada em `CHANGELOG.md`
5. Skill `actualiza-readme` — actualizar `README.md` se afectado (novas rotas, stack, instruções)
5b. **Contrato OpenAPI** — se a issue afectou endpoints ou schemas, actualizar `./openapi.yaml` (raiz) para reflectir o contrato real implementado; confirmar contra as rotas/Resources efectivas. Se a issue não tocou na API, não há alterações.
6. Commitar todos os artefactos de documentação:
   ```bash
   git add docs/debriefs/ docs/system_spec/ CHANGELOG.md README.md openapi.yaml
   git commit -m "docs(process): debrief + system_spec + changelog — Issue #N <slug>"
   ```
7. Actualizar `docs/workflow-state.md`:
   ```yaml
   fase: publica
   proximo_passo: /publica-implementacao #N
   debrief: docs/debriefs/YYYY-MM-DD-<slug>.md
   ```
8. Output final:
   ```
   ✅ Documentação concluída — Issue #N
   Debrief:    docs/debriefs/YYYY-MM-DD-<slug>.md
   SYSTEM_SPEC: actualizado
   Changelog:  actualizado
   README:     actualizado (ou sem alterações)
   OpenAPI:    actualizado (ou sem alterações)
   Próximo:    /publica-implementacao #N
   ```
