# Skill: escreve-debrief

Gera o Debrief da implementação a partir do git log e diff — síntese do que foi feito e porquê.

> **Categoria:** escreve  
> **Usado em:** `/documenta-implementacao` (passo 1)  
> **Produz:** `docs/debriefs/YYYY-MM-DD-<slug>.md`

## Contrato

**Input:**
```bash
git log main..HEAD --oneline
git diff main..HEAD --stat
git diff main..HEAD
```

**Output:** `docs/debriefs/YYYY-MM-DD-<slug>.md`

**Usado em:** `/documenta-implementacao` (passo 1)

---

## Formato do Debrief

```markdown
# Debrief: <título>

**Issue:** #N
**Branch:** feat/<slug>
**Data:** YYYY-MM-DD
**Commits:** N commits

## O que foi implementado
[Resumo conciso das alterações — o quê, não o porquê]

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| ...      | criado/alterado   | ...   |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| ...     | ...                     | ...         |

## Desvios ao Plano
[O que foi diferente do planeado, e porquê — "nenhum" se não houve]

## Aprendizagens
[O que ficou mais claro após implementar]

## SYSTEM_SPEC a actualizar
- `docs/system_spec/<ficheiro>.md` — [o que muda]

## Verificação final
- [ ] Linter a verde
- [ ] Testes a verde
- [ ] Nenhum dado sensível em logs
- [ ] Nenhum segredo em código
```

---

## Regras
- **"Decisões tomadas"** é a secção mais importante — é o que o utilizador confirma no Checkpoint D
- Desvios ao Plano devem ter justificação técnica
- Não incluir código no Debrief — referenciar ficheiros
