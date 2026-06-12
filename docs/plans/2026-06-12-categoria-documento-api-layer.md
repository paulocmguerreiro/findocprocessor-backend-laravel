# Plano — Issue #3: CategoriaDocumento API Layer

**Data:** 2026-06-12
**Branch:** feat/categoria-documento-api-layer
**Spec:** docs/specs/2026-06-12-categoria-documento-api-layer.md

---

## Tarefas

### T1 — `CategoriaDocumentoResource`

**Ficheiro:** `app/Features/CategoriaDocumento/CategoriaDocumentoResource.php`

- Estender `JsonResource`
- Implementar `toArray()` retornando: `id`, `nome`, `slug`, `tipo_movimento->value`
- PHPDoc com `@return array<string, string>` no `toArray()`
- `strict_types=1`

---

### T2 — `CriarCategoriaRequest`

**Ficheiro:** `app/Features/CategoriaDocumento/Criar/CriarCategoriaRequest.php`

- Estender `FormRequest`
- `authorize()` → `true`
- `rules()` com `Rule::unique()` e `Rule::in(array_column(TipoMovimento::cases(), 'value'))`
- `messages()` com todas as mensagens em português de Portugal
- `strict_types=1`

---

### T3 — `ActualizarCategoriaRequest`

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaRequest.php`

- Estender `FormRequest`
- `authorize()` → `true`
- `rules()` com `sometimes` + `Rule::unique()->ignore($this->route('categoria'))` + `Rule::in(...)`
- `messages()` com mensagens em português de Portugal (sem `*.required`)
- `strict_types=1`

---

### T4 — Testes `CategoriaDocumentoResourceTest`

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/CategoriaDocumentoResourceTest.php`

- Criar via factory, instanciar Resource, chamar `toArray()`
- Assertar presença de `id`, `nome`, `slug`, `tipo_movimento` (string)
- Assertar ausência de `created_at`, `updated_at`
- Assertar que `tipo_movimento` é o valor string do enum

---

### T5 — Testes `CriarCategoriaRequestTest`

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/CriarCategoriaRequestTest.php`

Usar `Validator::make()` directamente (sem HTTP) para testar `rules()` e `messages()`:

- `authorize()` → `true`
- Payload válido passa
- `nome` em falta → falha com mensagem `'O nome da Categoria é obrigatório.'`
- `slug` em falta → falha com mensagem `'O slug da Categoria é obrigatório.'`
- `tipo_movimento` em falta → falha com mensagem `'O tipo de movimento é obrigatório.'`
- `slug` duplicado (criar registo em BD) → falha com `'Já existe uma Categoria com este slug.'`
- `tipo_movimento` inválido → falha com `'O tipo de movimento indicado não é válido.'`

---

### T6 — Testes `ActualizarCategoriaRequestTest`

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaRequestTest.php`

- `authorize()` → `true`
- Payload só com `nome` → passa
- Payload vazio → passa
- `slug` igual ao registo actual (com `ignore`) → passa
- `slug` de outro registo → falha com `'Já existe uma Categoria com este slug.'`
- `tipo_movimento` inválido → falha com `'O tipo de movimento indicado não é válido.'`

---

### T7 — Qualidade

```bash
composer lint        # Pint
composer refactor    # Rector
composer test        # pipeline completa
```

Corrigir todos os erros antes de finalizar.

---

## Ordem de execução

```
T1 → T4 → T2 → T5 → T3 → T6 → T7
```

(implementação de cada componente seguida imediatamente pelo seu teste)

---

## SYSTEM_SPEC a actualizar (Fase 3)

- `docs/system_spec/02-shared.md` — secção Resources/FormRequests
