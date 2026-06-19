# Skill: actualiza-readme

Actualiza o `README.md` do repositório activo com base no Debrief — apenas se a implementação expõe novas rotas, altera o stack, ou muda instruções de uso.

> **Categoria:** actualiza  
> **Usado em:** `/documenta-implementacao` (passo 5)  
> **Produz:** `README.md` actualizado (ou sem alterações se não aplicável)

## Contrato

**Input:**
- `docs/debriefs/YYYY-MM-DD-<slug>.md` — secções "O que foi implementado" e "Ficheiros alterados"
- `README.md` actual do repositório

**Output:** `README.md` actualizado (apenas secções afectadas)

**Usado em:** `/documenta-implementacao` (passo 5)

---

## Quando actualizar

Actualizar o README apenas quando a implementação introduz **pelo menos uma** das seguintes alterações:

| Situação | Secção a actualizar |
|---|---|
| Nova rota ou endpoint API | "Estado actual" — adicionar à tabela da feature |
| Nova feature slice completa | "Estado actual" — adicionar nova subsecção |
| Alteração ao stack (nova dependência, ferramenta, versão) | "Stack" |
| Alteração ao comando de testes | "Testes" |
| Alteração ao processo de arranque (dev) | "Como correr (dev)" |
| Rota ou feature removida | "Estado actual" — remover da tabela |

**Não actualizar** quando as alterações são puramente internas: refactors, novos testes, novos DTOs, alterações de infra sem impacto na API pública.

---

## Regras

- Actualizar apenas as secções afectadas — nunca reescrever o ficheiro completo
- Manter o formato existente (tabelas Markdown, blocos de código)
- Se não houver alterações necessárias, registar "README sem alterações" e não criar commit
- Commit separado quando há alterações: `📝 docs: actualizar README após #N`
