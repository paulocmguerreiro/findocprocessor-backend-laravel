# Debrief — Issue #36: Autorização por roles/permissions

**Issue:** #36 — `feat(auth): autorização por roles/permissions — Spatie Laravel Permission + Policies`
**Data:** 2026-06-22
**Branch:** `feat/autorizacao-roles-permissions`
**Duração:** 1 sessão (continuada de sessão anterior)

---

## O que foi implementado

1. `spatie/laravel-permission ^8.0` instalado e configurado (guard `web`)
2. Data migration `seed_roles_and_permissions` — cria roles e permissions em todos os ambientes automaticamente com `php artisan migrate`
3. `HasRoles` adicionado ao model `User` com `@property-read` para `$roles` e `$permissions`
4. `EntidadePolicy` e `CategoriaDocumentoPolicy` substituídas de stubs `return true` para `hasPermissionTo()` real
5. `RolesPermissionsSeeder` de desenvolvimento — cria `admin@findocprocessor.test` com role `admin` e token Sanctum
6. 22 ficheiros de testes existentes actualizados: `admin` role + `forgetCachedPermissions()` no `beforeEach`
7. Novos testes: 7 cenários 403 para `utilizador` em operações de escrita; 4 cenários 200 para `utilizador` em leitura; 11 cenários `AuthorizationException` nas Actions (dupla camada)
8. System spec: `03-models/user.md` actualizado; `04-infra/autorizacao.md` criado; `00-index.md` actualizado

**Resultado:** 229 testes, 657 asserções, 100% cobertura, PHPStan nível 9 sem erros.

---

## Decisões tomadas

### 1. Guard `web` em vez de `sanctum`

O Brief original indicava `guard_name: 'sanctum'`. Durante a implementação, verificou-se que `config/auth.php` define apenas o guard `web` — Sanctum não regista um guard separado, autentica via middleware `auth:sanctum` que usa o driver de sessão/token do guard `web`. Usar `sanctum` como `guard_name` causaria falhas em `hasPermissionTo()` porque a permission não estaria registada no guard activo. Solução: omitir `guard_name` (usa o padrão `web`) em toda a configuração do Spatie.

### 2. Data migration para roles/permissions (não só seeder)

O Brief previu um seeder único. Durante a sessão, o utilizador perguntou como colocar os dados em produção — o seeder é de desenvolvimento, não corre automaticamente em prod. Solução: separar em duas camadas:
- **Data migration** `seed_roles_and_permissions` — roles e permissions, corre com `php artisan migrate` em qualquer ambiente, incluindo produção
- **`RolesPermissionsSeeder`** — apenas o utilizador admin de desenvolvimento

Esta decisão garante que novos ambientes (staging, prod) ficam com a estrutura de autorização correcta após `migrate`, sem necessidade de passos manuais.

### 3. `hasPermissionTo()` em vez de `hasRole()` nas Policies

Mais granular: permite atribuir permissions individuais sem alterar o role. Facilita a adição futura de roles intermédios sem reescrever as Policies.

### 4. Testes de dupla camada nas Actions

O utilizador apontou que os testes das Actions (invocação directa, não via HTTP) só cobriam o cenário autorizado. A dupla camada de autorização (`Gate::authorize()` no FormRequest **e** na Action) só é testada se houver cenários onde a Action é chamada por um utilizador sem permissão. Adicionados 11 testes `toThrow(AuthorizationException::class)` nas Action unit tests — um por Action.

---

## Desvios ao plano

| Desvio | Razão |
|---|---|
| Guard `web` em vez de `sanctum` | `config/auth.php` só define `web`; Sanctum não regista guard separado |
| Data migration separada para roles/permissions | Necessidade de deployar dados de autorização em produção sem passos manuais |
| T5 e T6 fundidos (Policies + todos os testes) | Mais eficiente implementar as Policies e actualizar todos os testes de uma vez |
| Rector aplicado no final (T8) | Rector adicionou return types em arrow functions de 6 ficheiros de testes — corrigido antes do commit |

---

## Ficheiros alterados

### Novos
- `config/permission.php` (publicado pelo Spatie)
- `database/migrations/2026_06_22_144003_create_permission_tables.php` (publicado pelo Spatie)
- `database/migrations/2026_06_22_150715_seed_roles_and_permissions.php`
- `database/seeders/RolesPermissionsSeeder.php`
- `tests/Unit/Models/UserTest.php`
- `tests/Unit/Policies/EntidadePolicyTest.php` (reescrito)
- `tests/Unit/Policies/CategoriaDocumentoPolicyTest.php`
- `docs/system_spec/04-infra/autorizacao.md`

### Modificados
- `composer.json` / `composer.lock`
- `app/Models/User.php`
- `app/Policies/EntidadePolicy.php`
- `app/Policies/CategoriaDocumentoPolicy.php`
- `database/seeders/DatabaseSeeder.php`
- 22 ficheiros de testes existentes (role `admin` + cache)
- `docs/system_spec/03-models/user.md`
- `docs/system_spec/00-index.md`

---

## Aprendizagens

### Vertical Slice e autorização: onde a dupla camada faz diferença

A regra de autorização dupla camada (`Gate::authorize()` no FormRequest + na Action) só fica evidente quando se testam as Actions directamente, fora do contexto HTTP. Nesta issue ficou claro que **sem os testes `AuthorizationException` nas Actions, a segunda camada de autorização seria código morto** — não estaria a ser exercida por nenhum teste.

O padrão Vertical Slice torna isto mais explícito: cada Action é uma unidade de lógica que pode ser invocada por um Job, por Artisan, por um teste directo — não apenas por um Controller. Isso significa que a autorização na Action não é redundante com a do FormRequest, mas complementar. Se um Job chamar uma Action diretamente sem passar pelo FormRequest, a autorização na Action é a única barreira.

### Data migrations como "seeds de estrutura"

A distinção entre dados de setup estrutural (roles e permissions) e dados de desenvolvimento (utilizador admin) é uma separação de responsabilidades importante. Os roles e permissions são análogos a enum values de base de dados — estrutura do domínio que deve existir em todos os ambientes. Colocá-los numa data migration em vez de num seeder garante que o sistema fica num estado válido após qualquer `php artisan migrate`, sem passos adicionais.

### Guard name no Spatie Laravel Permission

O Spatie suporta múltiplos guards (um role pode existir no guard `web` e outro no guard `api`). Quando a aplicação só tem um guard (`web`), omitir o `guard_name` é o comportamento correcto — o Spatie usa o guard por omissão. Tentar forçar `sanctum` como guard quando não está registado em `config/auth.php` causaria falhas silenciosas em `hasPermissionTo()`.

---

## Métricas

| Métrica | Valor |
|---|---|
| Testes totais | 229 |
| Testes novos (esta issue) | 42 |
| Asserções totais | 657 |
| Cobertura | 100% |
| PHPStan erros | 0 |
| Commits | 7 |
