# Skill: propoe-pr

Cria o Pull Request no GitHub após confirmação do utilizador (Checkpoint E).

> **Categoria:** propoe  
> **Usado em:** `/publica-implementacao` (passo 3)  
> **Produz:** URL do PR criado no GitHub

## Contrato

**Input:**
- `título`: `<type>(<scope>): <descrição>`
- `body`: PR body gerado por `/publica-implementacao`
- `branch`: branch activa (lida de `workflow-state.md`)
- `labels`: lidos da issue original
- `milestone`: lido da issue original

**Output:** URL do PR criado

**Usado em:** `/publica-implementacao` (passo 3)

---

## Comando

```bash
gh pr create \
  --repo $GITHUB_REPO \
  --title "<type>(<scope>): <descrição>" \
  --body "$(cat <<'EOF'
## O que muda
[resumo das alterações]

## Decisões técnicas
- [decisão 1]
- [decisão 2]

## Testes
- [ ] Unitários passaram
- [ ] Integração passaram
- [ ] Linter a verde
- [ ] Build a verde

## Verificação RGPD/NIS2
- Dados pessoais: [sim/não — detalhe]
- Superfície de ataque: [alterada/inalterada]

Closes #N
EOF
)" \
  --base main \
  --head <branch>
```

---

## Recuperação em caso de falha

1. Verificar se PR já existe (evitar duplicado):
   ```bash
   gh pr list --repo $GITHUB_REPO --head <branch> --json number,url,state
   ```
   - Se existir → mostrar URL e parar
2. Se falhou → skill `regista-aviso` com WRN-NNN e manter `workflow-state.md` com `fase: publica`
3. Mostrar:
   ```
   ⚠️ PR não criado — registado como WRN-NNN
   Para retomar: /publica-implementacao #N
   Para verificar auth: ! gh auth status
   ```

---

## Operações adicionais (invocação directa)

```bash
# Listar PRs abertos
gh pr list --repo $GITHUB_REPO

# Ver status de CI
gh pr checks <PR-number> --repo $GITHUB_REPO

# Merge após CI verde e review
gh pr merge <PR-number> --repo $GITHUB_REPO --squash --delete-branch
```

---

## Regras
- Nunca `--force-push` sem confirmação explícita do utilizador
- Nunca mergear com CI a vermelho
- Base branch é sempre `main`
- Deletar branch após merge
- Nunca adicionar rodapé "Generated with Claude Code" ou qualquer atribuição ao Claude no body do PR
