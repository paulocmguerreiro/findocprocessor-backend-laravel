---
description: Mostra o estado actual do workflow e próximo passo
allowed-tools: [Bash, Read]
effort: low
---

# /mostra-workflow

Mostra o estado actual do workflow — útil para retomar uma sessão após pausa.

## Argumentos
Nenhum.

## Passos

1. Verificar se `docs/workflow-state.md` existe:
   - **Não existe** → mostrar:
     ```
     ✅ Nenhuma sessão em curso.
     Para começar: /cria-issue <descrição>
     ```
   - **Existe** → ler e mostrar estado formatado:
     ```
     ⚠️ Sessão em curso detectada

     Issue:    #N — <título>
     Branch:   <branch>
     Fase:     <fase>
     Próximo:  <proximo_passo>

     Artefactos produzidos:
       Brief:   <path> [✅ existe | ❌ não encontrado]
       Spec:    <path> [✅ existe | ❌ não encontrado]
       Plano:   <path> [✅ existe | ❌ não encontrado]
       Debrief: <path se fase=publica> [✅ existe | ❌ não encontrado]
     ```

2. Verificar `docs/process-warnings.md`: correr `grep -n "^WRN-" docs/process-warnings.md` e listar
   **individualmente, uma linha por entrada**, todas as que tenham `STATUS: PENDENTE` ou
   `STATUS: PARCIALMENTE RESOLVIDO` (excluir `RESOLVIDO` e `IGNORADO`). Nunca agrupar, resumir ou
   omitir entradas por pertencerem à mesma issue/sessão — cada `WRN-NNN` conta por si, mesmo que haja
   5 entradas da mesma issue. O ficheiro só contém entradas accionáveis — `RESOLVIDO`/`IGNORADO` vivem
   em `docs/process-warnings-concluidos.md` (histórico, não lido aqui — ver
   `.claude/skills/regista-aviso.md`):
   ```
   ⚠️ Avisos de processo activos:
   - WRN-001 (PARCIALMENTE RESOLVIDO): <descrição curta>
   - WRN-014 (PENDENTE): <descrição curta>
   - WRN-019 (PENDENTE): <descrição curta>
   - WRN-020 (PENDENTE): <descrição curta>
   - WRN-021 (PENDENTE): <descrição curta>
   - WRN-022 (PENDENTE): <descrição curta>
   - WRN-023 (PENDENTE): <descrição curta>
   ```

3. Mostrar comandos disponíveis para a fase actual:
   ```
   Comandos disponíveis:
     /planeia-issue #N       (se sem sessão ou fase=planeia)
     /implementa-plano #N    (se fase=implementa)
     /documenta-implementacao #N  (se fase=documenta)
     /publica-implementacao #N    (se fase=publica)
   ```
