---
description: Fase 3b — PR no GitHub a partir do Debrief
allowed-tools: [Bash, Read]
model: sonnet
effort: medium
---

# /publica-implementacao

**Fase 3b** — Publica a implementação no GitHub: gera o PR body, aguarda revisão e cria o PR.
Requer `/documenta-implementacao` completo antes de executar.

## Argumentos
- `$ARGUMENTS`: número da issue (ex: `#5`) — opcional; se omitido, lê de `workflow-state.md`

## Pré-condições
1. Ler `docs/workflow-state.md` — confirmar `fase: publica`
2. Confirmar que `docs/debriefs/` tem o debrief desta issue
3. Verificar se PR já existe para evitar duplicado:
   ```bash
   gh pr list --repo $GITHUB_REPO --head <branch> --json number,url,state
   ```
   Se existir → mostrar URL e parar.
4. **Gate de paridade Docker/MySQL (local, falha fecha)** — só para stack Laravel:
   ```bash
   docker compose up -d --build
   docker compose exec -T app composer test         # suite contra MySQL (findocprocessor_testing)
   docker compose down
   ```
   Corre a mesma suite contra MySQL real, o mais próximo do stack de produção.
   Se falhar → **parar, NÃO publicar** e reportar o erro. Detalhe do ambiente:
   `docs/system_spec/04-infra/ambiente-docker.md`.

## Passos

1. Gerar PR body a partir do Debrief:
   ```markdown
   ## O que muda
   [resumo das alterações]

   ## Decisões técnicas
   [lista das decisões do Debrief]

   ## Testes
   - [ ] Unitários passaram
   - [ ] Integração passaram
   - [ ] Linter a verde
   - [ ] Build a verde

   ## Verificação RGPD/NIS2
   - Dados pessoais: [sim/não — detalhe]
   - Superfície de ataque: [alterada/inalterada]

   Closes #N
   ```
2. Skill `pausa-checkpoint` tipo=E — mostrar PR body completo e aguardar confirmação:
   ```
   📋 Checkpoint E — Revisão do PR
   [PR body]
   Confirmas que consegues defender cada decisão listada?
   ```
3. Skill `propoe-pr` → criar PR no GitHub
4. Recuperação se `gh pr create` falhar → skill `regista-aviso` (WRN-NNN) + manter `workflow-state.md`
5. Remover `docs/workflow-state.md` após PR criado com sucesso
6. Output final:
   ```
   ✅ PR criado — Issue #N
   PR: <URL>
   workflow-state removido. Issue encerra quando PR for merged.
   ```
