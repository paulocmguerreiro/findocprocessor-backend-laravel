# Skill: escreve-plan

Decompõe a Spec em tarefas concretas, ordenadas e commitáveis individualmente.

> **Categoria:** escreve  
> **Usado em:** `/planeia-issue` (passo 9)  
> **Produz:** `docs/plans/YYYY-MM-DD-<slug>.md`

## Contrato

**Input:**
- `docs/specs/YYYY-MM-DD-<slug>.md` — incluindo `## Dependências` e `## Riscos`
- `docs/briefs/YYYY-MM-DD-<slug>.md` — para herdar `## Riscos identificados` não capturados na Spec
- Secção `## ARQUITECTURA` do `CLAUDE.md` do repo activo

**Output:** `docs/plans/YYYY-MM-DD-<slug>.md`

**Usado em:** `/planeia-issue` (passo 9)

---

## Formato do Plano

```markdown
# Plano: <título>

**Issue:** #N
**Spec:** docs/specs/YYYY-MM-DD-<slug>.md
**Data:** YYYY-MM-DD

## Tarefas

### Tarefa 1 — <título>
- Ficheiros a criar/alterar: [lista]
- O que implementar: [detalhe técnico]
- Testes associados: [lista]
- Commit: `<type>(<scope>): <descrição>`

### Tarefa 2 — <título>
...

## Ordem de implementação

1. [Tarefa N] — porque [dependência]
2. [Tarefa M] — porque [dependência]

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| ...   | unit | ...      | ...      |

## Dependências
- Issues bloqueantes: [#N — título | "nenhuma"]
- Deve ser implementada após: [#N | "nenhuma"]

## Riscos de implementação
> Consolidados do Brief (`## Riscos identificados`) e da Spec — não apagar riscos do Brief.
- [risco 1]

## O que NÃO fazer nesta issue
- [limite explícito 1]
```

---

## Regras
- Cada tarefa é independente e commitável isoladamente
- A ordem respeita dependências entre camadas: `domain → application → infra → api`
- Testes escritos na mesma tarefa que o código (não numa tarefa separada "adicionar testes")
- Nunca antecipar tarefas de issues futuras
