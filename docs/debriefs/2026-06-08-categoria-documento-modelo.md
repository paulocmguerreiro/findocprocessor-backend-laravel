# Debrief — Issue #1: CategoriaDocumento — Camada de Modelo

**Data:** 2026-06-08
**Issue:** #1
**Branch:** `feat/categoria-documento-modelo`
**Duração estimada:** ~2h
**Estado:** ✅ Completo

---

## Resumo

Implementação da primeira entidade de domínio do FinDocProcessor: `CategoriaDocumento`. Cobre migration, enum `TipoMovimento`, Model Eloquent e Factory com states. Todos os testes passam (arch, types, unit, constraints BD). Pipeline `composer test` verde (excepto `test:coverage` que requer driver xdebug/pcov — ambiental, não código).

---

## O que foi construído

| Componente | Ficheiro | Estado |
|---|---|---|
| Enum `TipoMovimento` | `app/Shared/Enums/TipoMovimento.php` | ✅ |
| Migration | `database/migrations/..._create_categorias_documento_table.php` | ✅ |
| Model `CategoriaDocumento` | `app/Models/CategoriaDocumento.php` | ✅ |
| Factory `CategoriaDocumentoFactory` | `database/factories/CategoriaDocumentoFactory.php` | ✅ |
| Testes unitários | `tests/Unit/Models/CategoriaDocumentoTest.php` | ✅ |

---

## Critérios de aceitação

| CA | Descrição | Estado |
|---|---|---|
| CA-01 | Migration cria `categorias_documento` com UUID PK e índice único em `slug` | ✅ |
| CA-02 | Model tem `HasUuids`, `#[Fillable]`, `@property-read` em todas as colunas | ✅ |
| CA-03 | Cast de `tipo_movimento` para `TipoMovimento` enum funciona | ✅ |
| CA-04 | `TipoMovimento` é `BackedEnum` string com cases `Debito`, `Credito`, `Neutro` | ✅ |
| CA-05 | Factory base válida; states definem `tipo_movimento` correctamente | ✅ |
| CA-06 | `strict_types=1` em todos os ficheiros | ✅ |
| CA-07 | `composer test` passa (excl. coverage driver ambiental) | ✅ |

---

## Decisões tomadas

### 1. `casts()` método em vez de atributo `#[Casts]`

**Spec previa:** `#[Casts(['tipo_movimento' => TipoMovimento::class])]` como atributo PHP de classe.

**Implementado:** método `casts()` com `#[\Override]`:
```php
#[\Override]
protected function casts(): array
{
    return ['tipo_movimento' => TipoMovimento::class];
}
```

**Razão:** O atributo PHP `#[Casts]` existe no Laravel 13, mas o Larastan nível 9 não infere o tipo a partir do atributo para `@property-read`. O método `casts()` é a forma idiomática reconhecida pelo Larastan. Ambas as abordagens são funcionalmente equivalentes; o método tem melhor suporte de análise estática.

### 2. Testes de constraints BD adicionados (fora do plano original)

**Plano previa:** 8 testes (4 model + 4 factory states).

**Implementado:** 11 testes — acrescentados 3 testes de constraints da base de dados (slug único, UUID único, campos NOT NULL) usando `RefreshDatabase`.

**Razão:** Os testes de constraints validam invariantes críticos da migration de forma que os unit tests puros não conseguem. A cobertura real aumenta sem overhead significativo.

### 3. Fix no `ArchTest` — `.ignoring('App\Shared\Enums')`

**Problema:** O preset `laravel` do Pest 4 inclui uma regra que não aceita enums em namespaces fora de `App\Enums`. `TipoMovimento` em `App\Shared\Enums` causava falha: *"Expecting not to be enum"*.

**Fix:**
```php
arch()->preset()->laravel()->ignoring('App\Shared\Enums');
```

**Razão:** O namespace `App\Shared\Enums` é a localização correcta para enums partilhados entre features (per CLAUDE.md). O preset precisa de saber que enums aí vivem intencionalmente.

---

## Divergências da Spec

| Aspecto | Spec | Implementado | Impacto |
|---|---|---|---|
| Cast do enum | `#[Casts]` atributo PHP | `casts()` método | Funcionalidade idêntica; melhor suporte Larastan |
| Nº de testes | 8 | 11 (+ 3 constraints BD) | Mais cobertura |
| Factory `definition()` | `faker->words(2, true)` | `faker->word().' '.faker->word()` | Equivalente; formato idêntico |

---

## Problemas encontrados

| Problema | Causa | Solução |
|---|---|---|
| ArchTest falha com `TipoMovimento` | Preset `laravel` não aceita enums fora de `App\Enums` | `.ignoring('App\Shared\Enums')` |
| `test:coverage` sem driver | xdebug/pcov não instalado no ambiente de dev local | Ambiental — não afecta código nem CI |

---

## Aprendizagens

### Vertical Slice — onde vivem os enums partilhados?

A dúvida inicial foi: o `TipoMovimento` pertence a uma Feature slice ou a um namespace partilhado? A resposta ficou clara: enums que são usados por múltiplas features pertencem a `App\Shared\Enums`. Uma Feature slice contém apenas o que é exclusivo desse caso de uso. Um enum de tipo de movimento contabilístico é um conceito de domínio transversal — pertence ao shared kernel, não a uma slice.

### Model em `app/Models/` vs numa Feature slice

O `CategoriaDocumento` (Model Eloquent) vive em `app/Models/` (convencional Laravel), não numa Feature slice. Isto é intencional: o Eloquent Model é a representação de persistência da entidade, partilhada por múltiplas features. As Features têm Actions que operam sobre o Model via Repository — o Model em si não tem lógica de negócio.

### `#[Fillable]` e `#[Table]` como atributos PHP vs propriedades de classe

Laravel 13 suporta atributos PHP (`#[Fillable]`, `#[Table]`) em vez das propriedades tradicionais (`$fillable`, `$table`). São funcionalmente equivalentes. O atributo `#[Casts]` existe mas tem suporte de análise estática inferior ao método `casts()` — preferir o método para compatibilidade com Larastan nível 9.

### `HasUuids` gera UUIDv7

`HasUuids` no Laravel 13 gera automaticamente UUIDv7 (ordenável por tempo). Não é necessário override do método `newUniqueId()`. O formato é compatível com SQLite e MySQL. A chave primária é do tipo `string` — os testes devem verificar `getKeyType() === 'string'` e `getIncrementing() === false`.

### Pest preset `laravel` e namespaces de enums

O preset `arch()->preset()->laravel()` do Pest 4 reconhece `App\Enums` como namespace válido para enums, mas não `App\Shared\Enums`. Qualquer namespace personalizado de enums precisa de ser explicitamente ignorado com `.ignoring()`. Este padrão deve ser aplicado a outros namespaces custom que o preset não reconheça.

---

## Ficheiros modificados

```
A  app/Shared/Enums/TipoMovimento.php
A  database/migrations/2026_06_08_000001_create_categorias_documento_table.php
A  app/Models/CategoriaDocumento.php
A  database/factories/CategoriaDocumentoFactory.php
A  tests/Unit/Models/CategoriaDocumentoTest.php
M  tests/ArchTest.php                          (+ .ignoring('App\Shared\Enums'))
```

---

## Commits

```
41d69f1  feat: add TipoMovimento enum and CategoriaDocumento model layer
2f94bb2  test: add unit tests for CategoriaDocumento model and factory
```
*(fix ao ArchTest será commitado na Fase 3)*

---

## Próximo passo

Issue #2: Camada de persistência — `CategoriaDocumentoRepository` (interface + Eloquent + DTOs + testes).
