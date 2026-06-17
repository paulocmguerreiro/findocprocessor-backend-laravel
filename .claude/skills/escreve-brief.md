# Skill: escreve-brief

Expande a Issue num Brief estruturado que serve de base para a Spec.

> **Categoria:** escreve  
> **Usado em:** `/planeia-issue` (passo 4)  
> **Produz:** `docs/briefs/YYYY-MM-DD-<slug>.md`

## Contrato

**Input:**
- Issue body (lido via `gh issue view $N`)
- `docs/system_spec/*.md` relevantes ao âmbito da issue
- Resultados do MCP `search-docs` executado em `/planeia-issue` (passo 4) — usar para fundamentar os `## Riscos identificados` e `## Questões em aberto`

**Output:** `docs/briefs/YYYY-MM-DD-<slug>.md`

**Usado em:** `/planeia-issue` (passo 4)

---

## Formato do Brief

```markdown
# Brief: <título da issue>

**Issue:** #N
**Data:** YYYY-MM-DD
**Branch:** feat/<slug> | fix/<slug>

## Contexto
[Expandido a partir da issue — porquê existe este trabalho]

## O que muda
[Domínio / infra / API / UI afectado — ser específico sobre camadas e ficheiros]

## O que NÃO muda
[Limites explícitos do scope]

## Riscos identificados
[Técnicos, de processo, de qualidade]

## Questões em aberto
[O que precisa de decisão antes de implementar]
```

---

## Regras
- Não incluir detalhes de implementação — isso fica no Plan
- "O que NÃO muda" é tão importante quanto "O que muda"
- Riscos devem ser concretos, não genéricos ("migração pode falhar com dados existentes", não "há risco de bugs")
