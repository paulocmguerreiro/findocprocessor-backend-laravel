# Brief — Issue #30: Forçar update completo em CategoriaDocumento

**Data:** 2026-06-18
**Issue:** #30
**Branch:** feat/categoria-documento-update-completo

---

## Contexto

A feature `Actualizar/CategoriaDocumento` foi implementada com semântica PATCH (actualizações parciais): o `ActualizarCategoriaRequest` usava `sometimes` em todos os campos, e o `ActualizarCategoriaDto` tinha propriedades `?string` / `?TipoMovimento` para acomodar campos ausentes.

Na prática, o frontend nunca envia actualizações parciais — envia sempre o modelo completo. A semântica PATCH existia por precaução mas criava complexidade não justificada:

- Null guards condicionais no construtor do DTO (`$this->nome !== null && ...`)
- `array_filter` na Action para filtrar campos nulos antes do `fill()`
- Propriedades nullable enfraquecem o contrato Value Object: um DTO `ActualizarCategoriaDto(null, null, null)` é um estado inválido mas legítimo pelo tipo

A inconsistência foi detectada ao comparar o DTO de actualização com o de criação: `CriarCategoriaDto` tem `string` não-nullable; `ActualizarCategoriaDto` tinha `?string` sem motivo de negócio.

## Problema

O sistema permite estados no DTO que não têm significado de negócio (`ActualizarCategoriaDto(null, null, null)`). A validação no FormRequest não admite nulls (sem regra `nullable`), mas os tipos PHP admitem-nos — criando divergência entre contrato HTTP e contrato de domínio.

## Decisão

Forçar update completo (PUT semântico):
- `required` em vez de `sometimes` no FormRequest
- Propriedades não-nullable no DTO (mesmo padrão que `CriarCategoriaDto`)
- `fill()` directo na Action — sem filtragem de nulos

## Âmbito

| Ficheiro | Tipo de alteração |
|---|---|
| `ActualizarCategoriaRequest` | `sometimes` → `required`; mensagens `.required` |
| `ActualizarCategoriaDto` | Remover nullable; simplificar construtor e `@var` |
| `ActualizarCategoriaAction` | Remover `array_filter`; `fill()` directo |
| 4 ficheiros de testes | Actualizar para contrato de update completo |

## Riscos identificados

- **Clientes API existentes com PATCH parcial:** se algum cliente só enviar campos alterados, passará a receber 422. Risco nulo em fase de desenvolvimento sem clientes externos.
- **Rector/Pint:** alterações de tipos e formatação podem exigir ajuste pós-geração. Mitigado executando `composer lint` após cada ficheiro.

## Questões em aberto

- Nenhuma. Decisão tomada com base no comportamento real do frontend.

## Schema relevante (`categorias_documento`)

| Coluna | Tipo | Nullable |
|---|---|---|
| `id` | `char(36)` UUID v7 | não |
| `nome` | `varchar(255)` | não |
| `slug` | `varchar(255)` unique | não |
| `tipo_movimento` | `varchar(50)` | não |
