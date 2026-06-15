# Plano — Issue #10: Repository opcional em Vertical Slice

**Data:** 2026-06-15
**Issue:** [#10](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/10)
**Slug:** repository-opcional-vertical-slice
**Branch:** docs/repository-opcional-vertical-slice

---

## Tarefas

### T1 — Actualizar `CLAUDE.md`: secção "Padrões obrigatórios"

**Ficheiro:** `CLAUDE.md` linha 35
**Operação:** substituir regra absoluta por regra condicional com critérios

Substituir:
```
- Repositório entre Action e Eloquent Model
```
Por:
```
- Repositório entre Action e Eloquent Model — **obrigatório** quando há lógica de query complexa
  (joins, aggregates, raw SQL, queries partilhadas entre ≥ 2 Actions); **dispensável** em CRUD
  simples (≤ 1 query Eloquent por `handle()`, sem lógica partilhada); desvio sempre documentado no Brief
```

**Verificação:** `grep -n "Repositório" CLAUDE.md` mostra a nova regra

---

### T2 — Actualizar `CLAUDE.md`: secção "O que NÃO fazer"

**Ficheiro:** `CLAUDE.md` linha 101
**Operação:** qualificar a proibição absoluta com a excepção CRUD simples

Substituir:
```
- Não aceder directamente ao Eloquent Model nas Actions (usar Repository)
```
Por:
```
- Não aceder directamente ao Eloquent Model nas Actions sem Repository, excepto em CRUD simples
  (ver critérios em "Padrões obrigatórios")
```

**Verificação:** `grep -n "Repository\|Eloquent Model" CLAUDE.md` mostra a regra actualizada

---

### T3 — Commit

```bash
git add CLAUDE.md
git commit -m "docs(claude): clarificar quando Repository é dispensável em Vertical Slice (#10)"
```

---

## Ordem de execução

```
T1 → T2 → T3
```

## Estimativa

3 edições de texto, sem código. < 5 minutos.
