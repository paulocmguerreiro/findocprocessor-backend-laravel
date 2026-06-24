# Brief — Issue #37: Logging Estruturado (Actions, Controllers, erros, contexto de request)

**Data:** 2026-06-24
**Issue:** #37
**Branch:** feat/logging-estruturado-actions-middleware
**Slug:** logging-estruturado-actions-middleware

---

## Problema

Actualmente não existe logging em nenhuma camada da aplicação. Em produção é impossível:
- Auditar o que aconteceu (quem criou/actualizou/eliminou, quando)
- Diagnosticar erros (sem stack trace no log)
- Correlacionar logs de um mesmo request ou Job

---

## Contexto e Decisões de Design

### 1. Context facade vs Log::withContext()

A documentação do Laravel 13 confirma que **`Context::add()` é a abordagem correcta** para contexto partilhado. Diferença crítica:

| Mecanismo | Scope | Propaga para Jobs? |
|---|---|---|
| `Log::withContext()` | Canal actual | ❌ Não |
| `Log::shareContext()` | Todos os canais | ❌ Não (só durante request) |
| `Context::add()` | Todo o request + Jobs | ✅ Sim (dehydrate/hydrate automático) |

**Decisão:** `Context::add('trace_id', ...)` e `Context::addHidden('user_id', ...)` — o hidden impede que o user_id apareça em cada linha de log (redundante), mas fica disponível para Jobs e para correlação.

### 2. Posição do middleware na pipeline

O middleware `InjectarContextoLog` é registado **no grupo de rotas `api`**, depois de Sanctum resolver a autenticação. Isto garante que `Auth::id()` está disponível (CA-01). O `trace_id` é gerado no mesmo middleware.

Trade-off aceite: logs de erros de autenticação (401, pré-Sanctum) não têm `trace_id` — aceitável porque esses são tratados pelo exception handler antes do middleware do grupo.

### 3. Logging nas Actions — posição relativa à transação

Padrão decidido:
```
Gate::authorize(...)           ← autorização (sem log)
Log::info('xxx.criar.inicio')  ← APÓS autorização, ANTES da transação
DB::transaction(fn () => ...)  ← persistência
Log::info('xxx.criar.fim', ['id' => $result->id])  ← APÓS transação (só se commit)
```

- "início" só é logado se o utilizador tiver permissão (evita ruído de tentativas não autorizadas)
- "fim" só é logado se a transação fez commit (sem registos fantasma em caso de rollback)
- Dados sensíveis NUNCA no log — apenas IDs (UUID)

### 4. Logging de erros — exception handler global, não try-catch por Action

As Actions usam `DB::transaction()` que re-lança automaticamente qualquer `\Throwable`. Colocar try-catch em cada Action é redundante e viola o SRP.

**Decisão:** usar `$exceptions->report()` em `bootstrap/app.php` para logging global de excepções com contexto. O `trace_id` já está no Context e aparece automaticamente em todos os logs (incluindo os de excepção).

```php
$exceptions->report(function (Throwable $e): void {
    Log::error('excepção capturada', [
        'exception' => $e::class,
        'message'   => $e->getMessage(),
    ]);
    return false; // não impedir o reporting padrão do Laravel
});
```

---

## Riscos identificados

| Risco | Mitigação |
|---|---|
| NIF ou email nos logs (RGPD) | Nunca passar `$dados->nif`, `$dados->email` ao Log — apenas IDs UUID |
| user_id em logs pré-autenticação | Context::addHidden — apenas disponível internamente, não no output |
| Log duplicado em excepções | `return false` no `report()` para não bloquear o reporting padrão do Laravel; ou não chamar Log::error na Action |
| Middleware registado antes de Sanctum | Registar DENTRO do grupo `api` (onde Sanctum já corre) |

---

## Questões em aberto

Nenhuma — critérios de aceitação são claros e decisões de design foram tomadas.

---

### 5. Logging de tentativas de autenticação (CA-07 — novo)

Tentativas de login (com ou sem sucesso) devem ser sempre registadas para detectar brute force (múltiplas falhas do mesmo IP) e correlacionar sessões.

**Campos a registar:** email tentado, IP, user-agent. **NUNCA a password.**

**Origem dos dados — importante:**

| Campo | Origem | Confiança |
|---|---|---|
| `email` | `$request->validated()` — body validado | Dado pelo cliente (para log apenas) |
| `ip` | `$request->ip()` — REMOTE_ADDR / proxy infra | Resolvido pelo servidor / `TrustedProxies` |
| `agente` | `$request->userAgent()` — header HTTP | Pode ser falsificado — usar só para log, nunca para controlo de acesso |

IP e user-agent **não vêm do body da request** — vêm das propriedades HTTP resolvidas pelo servidor. Podem ser usados para logging/forensics mas nunca para decisões de segurança (acesso, permissões).

**Decisão — `LoginDto` com campos de contexto HTTP:**

Criar `LoginDto` (seguindo o padrão Value Object do projecto). O Controller faz `LoginDto::fromRequest($pedido)`:

```
LoginRequest → LoginDto::fromRequest($pedido) → LoginAction::handle(LoginDto)

LoginDto::fromRequest():
  email/password → $pedido->validated()   (body validado)
  ip             → $pedido->ip()          (REMOTE_ADDR — infra)
  agente         → $pedido->userAgent()   (header — só para log)
```

**Padrão de logging na LoginAction (excepção ao padrão geral):**

A `LoginAction` é a **única Action com try-catch explícito** — necessário para distinguir falha de autenticação (Log::warning) de erro de sistema (Log::error):

```
Log::info('auth.login.tentativa', ['email' => $dados->email, 'ip' => $dados->ip])

// falha de credenciais:
Log::warning('auth.login.falhou', ['email' => $dados->email, 'ip' => $dados->ip])

// sucesso:
Log::info('auth.login.sucesso', ['id_utilizador' => $utilizador->id, 'ip' => $dados->ip])
// (id_utilizador, não email — RGPD: mínimo necessário)
```

O `trace_id` já está no Context e aparece automaticamente em todos estes logs.

### 6. Posição do middleware — revisão

O middleware `InjectarContextoLog` fica no **grupo `api`** (nível de grupo), garantindo `trace_id` para TODOS os pedidos api/ — incluindo login (sem auth:sanctum).

**user_id:** o middleware corre antes de `auth:sanctum` (rota-level), pelo que `Auth::id()` é null nesse momento. Decisão: `user_id` não vai para Context no middleware — é passado explicitamente em cada log call das Actions protegidas (`'id_utilizador' => Auth::id()`), onde Auth já foi resolvido pelo Gate.

---

## Scope desta issue

**Inclui:**
- Middleware `InjectarContextoLog` com `Context::add('trace_id', ...)` no grupo `api`
- `LoginDto` com campos `email`, `password`, `ip`, `agente`
- Logging explícito na `LoginAction` (tentativa, falhou, sucesso)
- Log::info nas 7 Actions de escrita existentes (início + fim, com `'id_utilizador' => Auth::id()`)
- Log::error global via `$exceptions->report()` em `bootstrap/app.php`
- Canal `daily` em produção, `stack` em dev (`config/logging.php`)
- Spec `04-infra/logging.md`
- Testes (Unit: middleware, LoginDto; Feature: Log::spy na LoginAction e numa Action de escrita)

**Não inclui:**
- Audit trail before/after (issue futura)
- Rate limiting / bloqueio activo de brute force (issue de segurança separada)
- Log aggregation externo (Sentry, Datadog)
- Alertas em tempo real

---

## Ficheiros afectados

| Ficheiro | Acção |
|---|---|
| `app/Http/Middleware/InjectarContextoLog.php` | Criar |
| `bootstrap/app.php` | Alterar — registar middleware + `$exceptions->report()` |
| `config/logging.php` | Alterar — canal `daily` em prod |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php` | Alterar |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | Alterar |
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` | Alterar |
| `app/Features/Entidade/Criar/CriarEntidadeAction.php` | Alterar |
| `app/Features/Entidade/Actualizar/ActualizarEntidadeAction.php` | Alterar |
| `app/Features/Entidade/Eliminar/EliminarEntidadeAction.php` | Alterar |
| `app/Features/Entidade/EmpresaMae/ConverterEmEmpresaMaeAction.php` | Alterar |
| `docs/system_spec/04-infra/logging.md` | Criar |
| `docs/system_spec/00-index.md` | Alterar |
| `tests/Unit/Http/Middleware/InjectarContextoLogTest.php` | Criar |
| `tests/Feature/Features/CategoriaDocumento/CriarCategoriaActionLogTest.php` | Criar (exemplo) |

---

## Aprendizagens antecipadas

- `Context::add()` é superior a `Log::withContext()` para propagação cross-Jobs — fundamental em arquitectura com Queue
- O exception handler global é o sítio certo para `Log::error` — não nas Actions (SRP)
- Logging após `DB::transaction()` garante que "fim" só é registado se commit foi bem-sucedido
