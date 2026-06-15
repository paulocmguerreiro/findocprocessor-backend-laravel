# Brief — Issue #10: Repository opcional em Vertical Slice

**Data:** 2026-06-15
**Issue:** [#10](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/10)
**Slug:** repository-opcional-vertical-slice
**Tipo:** docs
**Branch:** docs/repository-opcional-vertical-slice

---

## Contexto

O `CLAUDE.md` define "Repositório entre Action e Eloquent Model" como padrão obrigatório (linha 35) e "Não aceder directamente ao Eloquent Model nas Actions (usar Repository)" na secção "O que NÃO fazer" (linha 101).

A implementação da issue #5 (CategoriaDocumento Actions) demonstrou que para CRUD simples — onde cada Action tem ≤ 1 query Eloquent e não há lógica de query partilhada — o Repository adiciona cerimónia sem valor: uma interface, uma implementação, um binding no ServiceProvider, testes extra. O Eloquent Model abstrai suficientemente o acesso a dados nesses casos.

A ausência de critérios explícitos para dispensar o Repository cria ambiguidade para futuras features: quando é obrigatório? quando é supérfluo?

---

## Problema a resolver

O `CLAUDE.md` não distingue entre:
- Features simples (CRUD, ≤ 1 query por Action) — Repository é overhead
- Features complexas (joins, aggregates, raw SQL, queries reutilizadas) — Repository é essencial

Resultado: a IA (e o developer) não sabem qual padrão aplicar sem consultar a issue #5 como referência implícita.

---

## Decisão adoptada na issue #5

Na implementação de `CategoriaDocumento`, as Actions acedem directamente ao Eloquent Model sem Repository. Justificativa:
1. Cada Action executa ≤ 1 query (`->create()`, `->findOrFail()`, `->update()`, `->delete()`)
2. Sem joins, aggregates, raw SQL ou lógica de query partilhada entre Actions
3. Eloquent Model abstrai suficientemente — não há SQL exposto nas Actions

---

## O que muda

**Apenas `CLAUDE.md`** — duas secções:
1. **Padrões obrigatórios** (linha 35): substituir a regra absoluta por uma regra condicional com critérios objectivos
2. **O que NÃO fazer** (linha 101): alinhar com a nova regra condicional

**Regra a documentar:**
- Repository é **obrigatório** quando: lógica de query complexa (joins, aggregates, raw SQL), queries reutilizadas entre ≥ 2 Actions, ou isolamento de testes é necessário
- Repository pode ser **omitido** quando: CRUD simples com ≤ 1 query Eloquent por `handle()`, sem lógica partilhada entre Actions
- O desvio deve ser **documentado explicitamente** no Brief da feature

---

## O que NÃO muda

- Código de features existentes — nenhum
- system_spec — nenhum ficheiro a actualizar (impacto puramente documental)
- openapi.yaml — não afectado
- Testes — não afectados

---

## Riscos

Nenhum. Alteração puramente documental. O risco seria deixar a ambiguidade actual, que pode levar à adição desnecessária de Repositories em features simples ou à inconsistência entre features.
