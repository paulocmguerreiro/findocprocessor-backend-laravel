# Spec — Issue #10: Repository opcional em Vertical Slice

**Data:** 2026-06-15
**Issue:** [#10](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/10)
**Slug:** repository-opcional-vertical-slice
**Branch:** docs/repository-opcional-vertical-slice

---

## Critérios de aceitação (da issue)

- [x] CA-01: `CLAUDE.md` contém secção ou nota que define quando o Repository **pode ser omitido**, com critérios objectivos:
  - Actions com código reduzido (≤ 1 query Eloquent por `handle()`)
  - Sem joins, aggregates, raw SQL, ou lógica de query reutilizada entre Actions
  - Eloquent Model abstrai suficientemente o acesso a dados
- [x] CA-02: `CLAUDE.md` mantém o Repository como **padrão obrigatório** para features com lógica de query complexa ou multi-step
- [x] CA-03: O desvio é documentado explicitamente por feature (ex: comentário no Controller ou nota no Brief)

---

## Alterações especificadas

### Ficheiro: `CLAUDE.md`

#### Secção "Padrões obrigatórios" — linha 35

**Antes:**
```
- Repositório entre Action e Eloquent Model
```

**Depois:**
```
- Repositório entre Action e Eloquent Model — obrigatório quando há lógica de query complexa
  (joins, aggregates, raw SQL, queries partilhadas entre ≥ 2 Actions); dispensável em CRUD
  simples (≤ 1 query Eloquent por `handle()`, sem lógica partilhada); desvio documentado no Brief
```

#### Secção "O que NÃO fazer" — linha 101

**Antes:**
```
- Não aceder directamente ao Eloquent Model nas Actions (usar Repository)
```

**Depois:**
```
- Não aceder directamente ao Eloquent Model nas Actions sem Repository, excepto em CRUD simples
  (ver critérios em "Padrões obrigatórios")
```

---

## Regra completa a implementar

```
Repository pattern:
  OBRIGATÓRIO quando:
    - joins, aggregates ou raw SQL
    - lógica de query partilhada entre ≥ 2 Actions
    - isolamento de testes exige mock do repositório

  DISPENSÁVEL quando:
    - CRUD simples: ≤ 1 query Eloquent por handle()
    - sem lógica de query partilhada entre Actions
    - Eloquent abstrai suficientemente (create/find/update/delete)

  SEMPRE:
    - documentar a decisão no Brief da feature
```

---

## Invariantes

- A regra existente não é eliminada — é qualificada com critérios explícitos
- Features novas complexas continuam a usar Repository
- Features existentes não são alteradas
- Nenhum código de produção é tocado

---

## Ficheiros a alterar

| Ficheiro | Tipo | Secção |
|----------|------|--------|
| `CLAUDE.md` | editar | Padrões obrigatórios (linha 35) |
| `CLAUDE.md` | editar | O que NÃO fazer (linha 101) |

---

## Ficheiros NÃO alterados

- `docs/system_spec/*.md` — sem impacto (a issue confirma explicitamente)
- `openapi.yaml` — não afectado
- Código de produção — nenhum
- Testes — nenhum
