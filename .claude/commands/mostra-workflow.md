---
description: Mostra o estado actual do workflow e próximo passo
allowed-tools: [Bash, Read]
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

2. Verificar `docs/process-warnings.md` — se tiver avisos não resolvidos, listar:
   ```
   ⚠️ Avisos de processo activos:
   - WRN-001: <descrição>
   ```

3. Mostrar comandos disponíveis para a fase actual:
   ```
   Comandos disponíveis:
     /planeia-issue #N       (se sem sessão ou fase=planeia)
     /implementa-plano #N    (se fase=implementa)
     /documenta-implementacao #N  (se fase=documenta)
     /publica-implementacao #N    (se fase=publica)
   ```
