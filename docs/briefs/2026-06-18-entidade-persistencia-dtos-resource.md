# Brief — Issue #32: Entidade — persistence layer (DTOs + resource + testes)

**Data:** 2026-06-18
**Issue:** #32
**Slug:** `entidade-persistencia-dtos-resource`
**Branch:** `feat/entidade-persistencia-dtos-resource`

---

## Contexto

Com o model layer da `Entidade` completo (issue #27), esta issue adiciona a camada de persistência de dados: os DTOs que encapsulam os dados de entrada com invariantes estruturais validados no construtor, e o Resource que serializa a resposta JSON.

Repositório Eloquent **dispensado** — CRUD simples sem queries complexas (sem joins, aggregates ou queries partilhadas entre ≥ 2 Actions). Desvio documentado conforme CLAUDE.md.

`fromRequest()` **não incluído** — adicionado na issue de lógica quando os FormRequests forem criados.

---

## Objectivo

Criar DTOs e Resource para a entidade `Entidade`, seguindo o padrão Value Object já estabelecido em `CategoriaDocumento`:
- `CriarEntidadeDto` — construtor valida invariantes; sem `fromRequest()` nesta fase
- `ActualizarEntidadeDto` — update completo (todos os campos obrigatórios); sem `fromRequest()`
- `EntidadeResource` — serialização JSON dos 6 campos do contrato
- Testes unitários cobrindo happy path + invariantes do construtor + serialização do Resource

---

## Decisões de design

| Decisão | Escolha | Justificação |
|---|---|---|
| Localização dos DTOs | `app/Features/Entidade/Criar/` e `app/Features/Entidade/Actualizar/` | Co-localizados com a acção — padrão Vertical Slice |
| Localização do Resource | `app/Features/Entidade/EntidadeResource.php` | Nível da slice, não em `app/Http/Resources/` |
| Validação de booleans | Não se valida trim | `bool` não tem "vazio" — a invariante é apenas `nome` e `nif` |
| `fromRequest()` | Ausente nesta issue | FormRequests criados na issue de lógica |
| Repositório | Dispensado | CRUD simples — sem lógica de query complexa |
| `eEmpresaAplicacao → eCliente/eFornecedor` | Fora do DTO | Invariante de negócio — pertence à Action |
| Timestamps no Resource | Omitidos | Padrão estabelecido em `CategoriaDocumentoResource` |

---

## Âmbito técnico

### Ficheiros a criar

```
app/Features/Entidade/Criar/CriarEntidadeDto.php
app/Features/Entidade/Actualizar/ActualizarEntidadeDto.php
app/Features/Entidade/EntidadeResource.php
tests/Unit/Features/Entidade/CriarEntidadeDtoTest.php
tests/Unit/Features/Entidade/ActualizarEntidadeDtoTest.php
tests/Unit/Features/Entidade/EntidadeResourceTest.php
```

### Nenhum ficheiro alterado (sem tocas no model, migration, factory, policy)

---

## Riscos identificados

- **Trim em strings com espaços:** os testes devem cobrir `'   '` (só espaços) como vazio — o trim deve falhar para invariante
- **Booleans no Resource:** `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` são casts `'boolean'` no model — o Resource deve devolver `bool`, não `int` (0/1)
- **`@mixin` do Resource:** `/** @mixin Entidade */` necessário para o Larastan inferir `$this->nome`, `$this->nif`, etc.
- **Cobertura 100%:** sem `fromRequest()` não há branches adicionais — a cobertura total deve ser mais directa

---

## Questões em aberto

- Nenhuma — âmbito bem definido na issue; padrão estabelecido em `CategoriaDocumento`

---

## Descobertas MCP search-docs

- `JsonResource::toArray()` retorna `array` — PHPDoc com array shape necessário para Larastan
- `toBeReadonly()` do Pest arch testing aplica-se a classes `readonly` — pode ser usado em testes de arquitectura se existirem
- Para testar invariantes do construtor: `expect(fn() => new Dto(...))->toThrow(InvalidArgumentException::class)` — padrão já usado nos testes de `CriarCategoriaDto`

---

## Dependências

- #27 — model layer `Entidade` (completo: `app/Models/Entidade.php` e `EntidadeFactory` existem)
- issue de lógica (futura) — adicionará `fromRequest()` aos DTOs quando os FormRequests forem criados
