# Debrief — Issue #10: Repository opcional em Vertical Slice

**Data:** 2026-06-15
**Issue:** [#10](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/10)
**Branch:** docs/repository-opcional-vertical-slice
**Estado:** concluída

---

## O que foi feito

Actualização de duas linhas no `CLAUDE.md` para substituir uma regra absoluta por uma regra condicional com critérios objectivos sobre quando o Repository pattern é obrigatório vs. dispensável.

**Alterações:**
- `CLAUDE.md:35` — "Padrões obrigatórios": regra absoluta → regra condicional com critérios (obrigatório/dispensável)
- `CLAUDE.md:101` — "O que NÃO fazer": proibição absoluta → qualificada com excepção CRUD simples

---

## Decisões tomadas

### D1 — Manter "obrigatório" como default, não "opcional"

A regra diz "obrigatório quando..." e "dispensável quando..." — não "opcional por defeito". Isto preserva a intenção original: o Repository é o padrão, e o desvio requer justificação consciente (documentada no Brief).

**Alternativa rejeitada:** inverter a lógica ("opcional por defeito, use quando necessário") — criaria pressão para nunca usar Repository, o que seria errado para features complexas.

### D2 — Critérios objectivos, não subjectivos

Os critérios são mensuráveis: "≤ 1 query Eloquent por `handle()`", "sem joins/aggregates/raw SQL", "sem lógica partilhada entre ≥ 2 Actions". Evita juízos de valor como "query simples" ou "CRUD básico" que cada developer interpreta diferente.

### D3 — Remissão cruzada entre secções

A secção "O que NÃO fazer" remete para "Padrões obrigatórios" com "(ver critérios em 'Padrões obrigatórios')". Evita duplicação de critérios e garante que há uma única fonte de verdade.

---

## O que ficou de fora

- Não foram alteradas features existentes — a issue explicitamente exclui isso
- Não foram actualizados os `system_spec/*.md` — a issue confirma que nenhum é afectado
- Não foi adicionada uma secção completa dedicada ao Repository pattern — a regra inline na lista de padrões é suficiente para o nível de detalhe do `CLAUDE.md`

---

## Aprendizagens

### Vertical Slice e o Repository: quando a arquitectura se adapta à realidade

Esta issue cristalizou uma tensão real em Vertical Slice Architecture: o Repository pattern é ensinado como "boa prática universal", mas em VSA com Actions coesas, ele pode ser overhead puro.

**O que ficou mais claro:**

Em Vertical Slice, a unidade de coesão é a _feature_, não a _camada_. Quando uma feature é CRUD simples (listar, criar, ver, actualizar, eliminar), cada Action tem uma responsabilidade tão estreita que a única "lógica de query" é `->create()`, `->findOrFail()`, etc. — métodos que o Eloquent Model já fornece como interface estável.

O Repository resolve dois problemas concretos:
1. **Queries complexas reutilizadas** entre várias Actions (ex: uma query com 3 joins que é usada em listar, exportar e validar)
2. **Isolamento de testes** quando queremos mockar o acesso a dados sem tocar na BD

Quando nenhum destes problemas existe, o Repository é uma camada de indireção sem benefício — e em VSA isso é especialmente visível porque o overhead (interface + implementação + binding + testes) é relativo à feature inteira, não ao sistema global.

**Regra mnemónica que ficou:** _"Se a query cabe num método Eloquent nativo, o Eloquent é o Repository."_

Esta distinção entre "padrão por default" e "padrão obrigatório incondicionalmente" é precisamente o tipo de nuance que Vertical Slice encoraja: não há camadas sagradas, há decisões conscientes por feature.
