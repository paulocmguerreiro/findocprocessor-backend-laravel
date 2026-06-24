---
description: Fase 2 — Implementação tarefa a tarefa com checkpoint e testes
allowed-tools: [Bash, Read, Write, Edit]
---

# /implementa-plano

**Fase 2** — Implementa o Plano tarefa a tarefa, com checkpoint por tarefa e testes no final.

## Argumentos
- `$ARGUMENTS`: número da issue (ex: `#5`) — opcional; se omitido, lê de `workflow-state.md`
- `--stack`: `dotnet` | `laravel` | `angular` | `all` — opcional; se omitido, usa o repo activo
  - `--stack all` → lança `executa-agent-multi-stack` (3 agents em paralelo)

## Pré-condições
1. Ler `docs/workflow-state.md` — confirmar `fase: implementa`
2. Ler `docs/plans/YYYY-MM-DD-<slug>.md` — lista de tarefas
3. Verificar branch activa: `git branch --show-current`
4. Se pré-condição falhar → skill `regista-aviso` e avisar utilizador

## Loop por cada tarefa do Plano

### Anunciar
```
▶ Tarefa N/T: <título>
```

### Pesquisar antes de implementar
**MCP `search-docs`** — antes de escrever qualquer código, pesquisar a documentação relevante para esta tarefa:
- Identificar o conceito Laravel/Pest concreto a implementar (ex: `refresh eloquent`, `policy gate`, `paginate`)
- Executar 1-2 queries focadas no que vai ser escrito nesta tarefa (ex: `"model refresh"`, `"gate authorize"`)
- Se a tarefa envolver queries ou modelos: executar `database-schema` para confirmar estrutura actual
- Se a tarefa envolver testes: executar `search-docs` com query temática de Pest (ex: `"pest mock"`, `"pest dataset"`)
- Usar os resultados para confirmar a API correcta antes de escrever — não assumir com base em treino

### Implementar
Implementar apenas o código desta tarefa. Não antecipar tarefas seguintes.

### Lint + Refactor (antes de commitar)
Para stack Laravel, executar antes de cada checkpoint:
```bash
composer lint      # Pint — formatação
composer refactor  # Rector — modernizações PHP/Laravel
```
Se houver alterações, incluí-las nos ficheiros do checkpoint. Não commitar código sem passar pelo Pint e Rector.

### Definition of Done — paridade Docker
Se a tarefa alterou **`composer.json`**, **extensões PHP necessárias** ou **`.env.example`**:
→ actualizar `Dockerfile` / `compose.yaml` / `.dockerignore` em conformidade e
incluir essas alterações no **mesmo** checkpoint. Não deixar o setup Docker
desactualizado em relação ao código. Detalhe: `docs/system_spec/04-infra/ambiente-docker.md`.

### Checkpoint por tarefa
Skill `pausa-checkpoint` tipo=task — mostrar ficheiros alterados e aguardar resposta:
```
✋ Checkpoint — Tarefa N implementada

Ficheiros alterados:
- <lista>

Leste o código? Responde:
  [s]       → commit e avançar
  [explica] → explicar decisões antes de commitar
  [altera]  → descrever o que alterar
```

### Propor commit
Skill `propoe-commit` — proposta formatada, aguarda confirmação antes de executar.

## Após todas as tarefas

1. Skill `executa-testes` — auto-retry até 3x; se persistir → skill `regista-aviso`
2. Se stack = `laravel`: skill `executa-checkpoint-scan` — scan de segurança/qualidade; pausa se FAIL
3. Skill `pausa-checkpoint` tipo=② — resumo de implementação + confirmação antes de avançar
4. Actualizar `docs/workflow-state.md`:
   ```yaml
   fase: documenta
   proximo_passo: /documenta-implementacao #N
   ```
5. Output final:
   ```
   ✅ Fase 2 concluída — Issue #N
   Tarefas: N/N  |  Testes: ✅  |  Scan: ✅ (ou 🔴 N FAILs confirmados)
   Próximo: /documenta-implementacao #N
   ```

## Execução isolada (opcional)
Para stack único: `executa-agent-implementar-plano`
Para todos os stacks: `executa-agent-multi-stack` → delega para 3 agents em paralelo
