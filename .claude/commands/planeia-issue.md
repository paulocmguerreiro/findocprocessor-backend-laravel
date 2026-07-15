---
description: Fase 1 — Brief + Branch + Spec + Plano para uma Issue
allowed-tools: [Bash, Read, Write]
model: opus
effort: high
---

# /planeia-issue

**Fase 1** — Planeia a implementação de uma Issue: Brief → Branch → Spec → Plano → workflow-state

## Argumentos
- `$ARGUMENTS`: número da issue (ex: `#5`) — opcional; se omitido, usa skill `escolhe-issue`

## Pré-condições
- Se `docs/workflow-state.md` existir → avisar sessão em curso e aguardar confirmação
- Verificar `docs/process-warnings.md` — reportar avisos activos no início

## Passos

1. Se sem argumento → skill `escolhe-issue`
2. Ler issue: `gh issue view $N --repo $GITHUB_REPO`
3. Ler `docs/system_spec/*.md` relevantes para o contexto
4. **MCP `search-docs`** — pesquisar documentação dos conceitos Laravel/Pest envolvidos na issue:
   - Identificar os tópicos chave (ex: `eloquent refresh`, `model policy`, `pagination`, `PHPDoc throws`)
   - Executar 1-3 queries broad (ex: `"eloquent model refresh"`, `"policy authorization gate"`)
   - Se a issue envolver modelos ou migrations: executar também `database-schema`
   - Registar descobertas relevantes nos `## Riscos identificados` e `## Questões em aberto` do Brief
5. Skill `escreve-brief` → `docs/briefs/YYYY-MM-DD-<slug>.md`
6. Skill `pausa-checkpoint` tipo=A
7. Criar branch: `git checkout -b feat/<slug>` (ou `fix/<slug>` se type=fix)
8. Skill `escreve-spec` → `docs/specs/YYYY-MM-DD-<slug>.md`
9. Skill `pausa-checkpoint` tipo=B
10. Skill `escreve-plan` → `docs/plans/YYYY-MM-DD-<slug>.md`
10.5. Skill `executa-triagem-semantica` alvo=plano — confronta os nomes de ficheiros/classes/métodos
     previstos no Plano com os specs relevantes (nomenclatura, interfaces `Contrato<Nome>`, etc.);
     sinaliza violações e ajusta o Plano só se confirmado pelo utilizador.
11. Escrever `docs/workflow-state.md`:
    ```yaml
    issue_number: N
    issue_title: <título>
    slug: <slug>
    branch: feat/<slug>
    fase: implementa
    proximo_passo: /implementa-plano #N
    brief: docs/briefs/YYYY-MM-DD-<slug>.md
    spec: docs/specs/YYYY-MM-DD-<slug>.md
    plan: docs/plans/YYYY-MM-DD-<slug>.md
    ```
12. Commitar todos os artefactos de planeamento:
    ```bash
    git add docs/briefs/ docs/specs/ docs/plans/ docs/workflow-state.md
    git commit -m "docs(process): brief + spec + plan — Issue #N <slug>"
    ```
13. Output final:
    ```
    ✅ Fase 1 concluída — Issue #N
    Branch:  feat/<slug>
    Brief:   docs/briefs/YYYY-MM-DD-<slug>.md
    Spec:    docs/specs/YYYY-MM-DD-<slug>.md
    Plano:   docs/plans/YYYY-MM-DD-<slug>.md
    Próximo: /implementa-plano #N
    ```
