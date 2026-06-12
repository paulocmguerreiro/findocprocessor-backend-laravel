# Brief — Issue #3: CategoriaDocumento API Layer

**Data:** 2026-06-12
**Branch:** feat/categoria-documento-api-layer
**Issue:** #3 — feat(laravel): CategoriaDocumento — API layer (Resource + FormRequests)

---

## Objectivo

Criar os contratos de transferência de dados para a feature `CategoriaDocumento`:
um `JsonResource` para formatação de output e dois `FormRequest` para validação de input (criar e actualizar).

## Contexto técnico

- Modelo `CategoriaDocumento` já existe (`app/Models/CategoriaDocumento.php`) com UUID PK e campos `nome`, `slug`, `tipo_movimento` (cast para enum `TipoMovimento`)
- Arquitectura Vertical Slice → tudo em `app/Features/CategoriaDocumento/`
- Larastan nível 9, 100% coverage, `strict_types=1` obrigatório

## Decisões de design

1. `CategoriaDocumentoResource` expõe apenas `id`, `nome`, `slug`, `tipo_movimento->value` — timestamps omitidos intencionalmente
2. `CriarCategoriaRequest`: todos os campos `required`; `Rule::unique()` para slug; `Rule::in()` para tipo_movimento; `messages()` com mensagens em português
3. `ActualizarCategoriaRequest`: campos com `sometimes`; `Rule::unique()->ignore($uuid)` para excluir o registo actual; mesmas mensagens personalizadas (sem `*.required`)
4. `authorize()` retorna `true` — autenticação tratada noutro layer

## Fora de âmbito

- Repositório e interface Eloquent (issue separada)
- Controller e Actions (issue separada)
- Rotas e endpoints de API