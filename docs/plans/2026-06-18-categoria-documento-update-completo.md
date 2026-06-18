# Plano — Issue #30: Forçar update completo em CategoriaDocumento

**Data:** 2026-06-18
**Issue:** #30

---

## Tarefas

### T1 — `ActualizarCategoriaRequest`: `sometimes` → `required`

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaRequest.php`

- Substituir `sometimes` por `required` nas três regras
- Adicionar mensagens `.required` para `nome`, `slug`, `tipo_movimento`

### T2 — `ActualizarCategoriaDto`: remover nullable

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php`

- `?string $nome` → `string $nome`
- `?string $slug` → `string $slug`
- `?TipoMovimento $tipoMovimento` → `TipoMovimento $tipoMovimento`
- Remover null guards condicionais (`$this->nome !== null &&`) → validar incondicionalmente
- Actualizar `@var` array shape: `array{nome?: ...}` → `array{nome: ...}`
- `fromRequest()`: remover `?? null`; `TipoMovimento::from()` directo

### T3 — `ActualizarCategoriaAction`: remover `array_filter`

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php`

- Substituir `array_filter([...], fn(...) => $valor !== null)` por array literal directo
- `$categoria->fill([nome, slug, tipo_movimento])->save()`

### T4 — Testes unitários do DTO

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaDtoTest.php`

- Remover testes de actualização parcial (campos nulos)
- Actualizar chamadas ao construtor: passar todos os campos
- Adicionar asserção da mensagem de excepção (`.toThrow(..., 'mensagem')`)
- Adicionar teste de whitespace-only no `nome`
- Adicionar teste de DTO completo válido
- Actualizar `fromRequest()` mock para passar os 3 campos

### T5 — Testes unitários do Request

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaRequestTest.php`

- Remover testes "aceita payload vazio" e "aceita payload só com nome"
- Adicionar helper `payloadCompleto(array $sobrepor = [])` para evitar repetição
- Adicionar testes de rejeição por campo ausente (nome, slug, tipo_movimento)
- Actualizar testes de unicidade para usar `payloadCompleto()`

### T6 — Testes unitários da Action

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php`

- Actualizar instanciação do DTO: passar `slug` e `tipoMovimento` explicitamente
- Adicionar asserção do `slug` e `tipoMovimento` no resultado

### T7 — Testes de feature (integração)

**Ficheiro:** `tests/Feature/Features/CategoriaDocumento/ActualizarCategoriaTest.php`

- Adicionar helper `payloadActualizar(array $sobrepor = [])` local
- Renomear "actualiza campos enviados" → "actualiza todos os campos"
- Remover teste de update parcial de `tipo_movimento` isolado
- Adicionar teste de rejeição de payload vazio (422 com estrutura de erros)
- Actualizar todos os `putJson()` para enviar payload completo

### T8 — `composer lint` + `composer test`

Garantir Pint, Rector, PHPStan e Pest todos verdes antes de commitar.

---

## Sequência recomendada

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8
```

Dependência linear: a Action depende do DTO; os testes dependem do contrato final dos três ficheiros de produção.

---

## Notas

- Não alterar rota, controller, resource nem policy
- O Pint aplica `binary_operator_spaces` nos arrays — correr `composer lint` após T3
- O `@var` array shape no `fromRequest()` passa de `array{nome?: string}` para `array{nome: string}` — crítico para PHPStan nível 9
