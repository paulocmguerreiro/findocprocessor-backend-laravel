# Skill: escolhe-issue

Selecciona automaticamente a próxima issue pronta para implementar, com base em dependências e prioridade.

> **Categoria:** escolhe  
> **Usado em:** `/planeia-issue` (quando sem argumento), `/cria-issue` (verificação de conflitos)  
> **Produz:** número da issue seleccionada + confirmação do utilizador

## Contrato

**Input:** nenhum (lê directamente do GitHub)

**Output:** número da issue seleccionada + confirmação do utilizador

**Usado em:** `/planeia-issue` (quando sem argumento), `/cria-issue` (verificação de conflitos)

---

## Lógica

```bash
gh issue list --repo $GITHUB_REPO --state open --json number,title,labels,body
```

### Algoritmo de selecção
1. Para cada issue aberta, extrair o campo `**Dependências:**` do body
2. Para cada dependência, verificar se está fechada:
   ```bash
   gh issue view <dep_number> --repo $GITHUB_REPO --json state
   ```
3. Issues **prontas** = todas as dependências fechadas (ou "Independente")
4. Ordenar por prioridade:
   - `type:fix` primeiro
   - Em empate: número mais baixo (FIFO)

### Apresentação ao utilizador
```
Issues prontas para implementar:

  #12 ✨ feat: Endpoint /upload (Independente)
  #15 🐛 fix: Corrigir estado ERROR (Independente)  ← recomendada
  #17 ✨ feat: SSE /events (depende de #12 — fechada ✅)

Recomendação: #15 (fix tem prioridade sobre feat)

Confirmar? [sim / outra / cancelar]
```

- `sim` → retorna #15
- `outra` → utilizador indica o número manualmente
- `cancelar` → abortar o command
