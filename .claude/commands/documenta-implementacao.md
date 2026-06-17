---
description: Fase 3a — Debrief + SYSTEM_SPEC + Changelog (artefactos locais)
allowed-tools: [Bash, Read, Write, Edit]
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
3. Skill `actualiza-spec` — actualizar `docs/system_spec/*.md` conforme `SYSTEM_SPEC_MAP` do `CLAUDE.md`
4. Skill `actualiza-changelog` — adicionar entrada em `CHANGELOG.md`
5. Actualizar `README.md` se afectado — apenas secções alteradas, não reescrever completo
6. Commitar todos os artefactos de documentação:
   ```bash
   git add docs/debriefs/ docs/system_spec/ CHANGELOG.md README.md
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
   Próximo:    /publica-implementacao #N
   ```

## Execução isolada (opcional)
Lançar `executa-agent-documentar-implementacao` para isolar o contexto de documentação da sessão principal.
