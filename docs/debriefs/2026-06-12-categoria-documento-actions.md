# Debrief — Issue #5: CategoriaDocumento — Actions + Controller

**Data:** 2026-06-12
**Branch:** `feat/categoria-documento-actions`
**Issue:** #5
**Duração:** sessão única

---

## O que foi implementado

Feature slice CRUD completo para `CategoriaDocumento`:

- **5 Actions** — `ListarCategoriasAction`, `CriarCategoriaAction`, `VerCategoriaAction`, `ActualizarCategoriaAction`, `EliminarCategoriaAction`
- **2 DTOs** — `CriarCategoriaDto`, `ActualizarCategoriaDto` (`final readonly`)
- **1 Controller** — `CategoriaDocumentoController` sem lógica, puro dispatch para Actions
- **Rotas** — `Route::apiResource('categorias-documento', ...)` → 5 endpoints REST
- **Testes** — 5 ficheiros feature (um por operação) + unit tests para DTOs e Actions
- **Fix** — `ActualizarCategoriaRequest` corrigido para parâmetro `categorias_documento` (route model binding)

Pipeline: 62 testes, 188 assertions, 100% coverage, Larastan nível 9 sem erros.

---

## Decisões tomadas

### D1 — Route Model Binding no Controller; Actions aceitam `CategoriaDocumento|string`

**Plano:** Actions receberiam `string $idCategoria` e chamariam `findOrFail` internamente.

**Real:** O Controller usa Route Model Binding — o Laravel injeta o modelo directamente. Actions foram adaptadas para aceitar `CategoriaDocumento|string`:

```php
public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
{
    return is_string($idCategoria)
        ? CategoriaDocumento::findOrFail($idCategoria)
        : $idCategoria;
}
```

**Porquê:** Route Model Binding elimina boilerplate no Controller e devolve 404 automaticamente antes de chegar à Action. O union type mantém as Actions testáveis sem contexto HTTP — basta passar um UUID string nos testes unitários.

### D2 — DTOs com `is_string()` guards após `validated()`

**Plano:** `$request->string('campo')` para extrair valores.

**Real:** `$request->validated()` + guards `is_string()` explícitos, com `UnexpectedValueException` se falhar.

**Porquê:** Larastan nível 9 não consegue inferir que `$validated['nome']` é `string` — vê `mixed`. `$request->string()` levantou outro problema: devolve `Stringable`, não `string` nativa, e as propriedades do DTO são `string`. A solução `validated()` + `is_string()` satisfaz PHPStan e é defensiva (nunca chega a `from()` com valor inesperado).

### D3 — Sem Repository (desvio explícito CLAUDE.md)

Documentado no Brief. Actions acedem directamente ao Eloquent. Decisão deliberada para CRUD simples sem lógica de negócio adicional.

### D4 — `array_filter` com `!== null` para partial update

```php
$campos = array_filter([...], fn (mixed $v): bool => $v !== null);
```

`false` e `0` são válidos neste domínio? Não existem campos booleanos ou numéricos em `CategoriaDocumento`, por isso `array_filter` com `!== null` é seguro. Se aparecerem no futuro, a condição terá de mudar.

---

## O que correu bem

- Pipeline de qualidade (lint → refactor → test) como rito por tarefa detectou erros de tipagem cedo — PHPStan apontou os `mixed` antes dos testes falharem.
- A arquitectura Vertical Slice manteve o scope contido — cada ficheiro sabia exactamente o que fazia e onde vivia.
- Route Model Binding + `CategoriaDocumento|string` é um padrão elegante: o Controller beneficia da magia do Laravel; os testes unitários mantêm-se puros.

---

## O que foi mais difícil

- **Larastan + `validated()`** — a luta entre conveniência (`$request->string()`) e tipagem rigorosa ocupou mais tempo do que o esperado. A solução final (`is_string()` guards) é verbosa mas correcta.
- **Arch tests** — os testes arquitecturais falharam inicialmente porque as Actions usam `CategoriaDocumento` (Eloquent Model) directamente. O preset `laravel` marcava isso como violação. Solução: `.ignoring()` cirúrgico no arch test para a excepção documentada.

---

## Aprendizagens (Vertical Slice + Actions + PHP)

### Route Model Binding como camada de resolução — não como magia opaca

O Route Model Binding não é apenas "conveniência" — é uma camada de resolução explícita que o Laravel executa antes do Controller. Ao aceitar `CategoriaDocumento|string` nas Actions, percebo que o padrão mais correcto para Vertical Slice é: **o Controller usa RMB quando vem do HTTP; a Action aceita o modelo ou o ID quando chamada directamente**. Isto separa o transporte (HTTP) da lógica (Action) sem quebrar a testabilidade.

### PHPStan nível 9 força a ser explícito sobre `mixed`

`$request->validated()` devolve `array<string, mixed>`. Cada acesso `$validated['campo']` é `mixed`. Nível 9 não aceita atribuir `mixed` a `string` sem guard. Esta fricção não é ruído — é o compilador a dizer "prova que este dado é o que dizes que é". A guard `is_string()` + `UnexpectedValueException` é a resposta correcta: nunca chega a dados inválidos ao domínio.

### `final readonly` DTOs — imutabilidade como documentação

DTOs `final readonly` comunicam intenção: "este objecto não muda após construção e não é base para subclasses". Em PHP 8.2+, `readonly` em classe aplica-se a todas as propriedades — menos cerimónia, mais clareza. A imutabilidade torna o fluxo de dados previsível: o DTO que sai do FormRequest é o mesmo que chega à Action.

### `array_filter` com closure `!== null` — partial update limpo

O padrão `array_filter($campos, fn($v) => $v !== null)` é a forma idiomática de PATCH parcial em PHP sem enums de sentinela. Mas tem um limite: `false` e `0` são filtrados se não houver a guarda `!== null` explícita. Conhecer este limite é mais valioso do que usar a função sem o perceber.

---

## Ficheiros criados/editados

| Ficheiro | Operação |
|---|---|
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaRequest.php` | Editado (fix parâmetro rota) |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` | Criado |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` | Criado |
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` | Criado |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php` | Criado |
| `app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php` | Criado |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | Criado |
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` | Criado |
| `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` | Criado |
| `routes/api.php` | Editado |
| `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` | Criado |
| `tests/Feature/Features/CategoriaDocumento/CriarCategoriaTest.php` | Criado |
| `tests/Feature/Features/CategoriaDocumento/VerCategoriaTest.php` | Criado |
| `tests/Feature/Features/CategoriaDocumento/ActualizarCategoriaTest.php` | Criado |
| `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` | Criado |

---

## Métricas finais

| Métrica | Valor |
|---|---|
| Testes totais | 62 |
| Assertions | 188 |
| Cobertura | 100% |
| Type coverage | 100% |
| Larastan erros | 0 |
| Rector sugestões | 0 |
| Pint erros | 0 |
