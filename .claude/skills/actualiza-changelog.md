# Skill: actualiza-changelog

Adiciona uma entrada ao `CHANGELOG.md` no formato Keep a Changelog.

> **Categoria:** actualiza  
> **Usado em:** `/documenta-implementacao` (passo 4)  
> **Produz:** `CHANGELOG.md` actualizado

## Contrato

**Input:**
- `docs/debriefs/YYYY-MM-DD-<slug>.md` — secção "O que foi implementado"
- Número da issue (`#N`)
- Tipo de alteração: Added | Changed | Fixed | Removed | Security

**Output:** `CHANGELOG.md` actualizado

**Usado em:** `/documenta-implementacao` (passo 4)

---

## Formato

```markdown
## [Unreleased]

### Added
- <descrição da nova funcionalidade> (#N)

### Changed
- <descrição de alteração de comportamento> (#N)

### Fixed
- <descrição de correcção de bug> (#N)
```

---

## Regras
- Adicionar sempre na secção `[Unreleased]` — nunca criar nova versão manualmente
- Uma linha por alteração significativa — não listar ficheiros individuais
- Referenciar sempre o número da issue `(#N)`
- Commit separado: `📝 docs: actualizar changelog após #N`
