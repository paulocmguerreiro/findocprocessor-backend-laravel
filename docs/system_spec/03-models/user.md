# System Spec — Model: User

> `app/Models/User.php`

Modelo de autenticação Laravel. Representa o utilizador da aplicação.

---

## Excepção documentada: PK inteira

O model `User` usa PK `int $id` autoincremental — **excepção intencional** à regra de `HasUuids` dos modelos de domínio. Razão: é o modelo de autenticação do Laravel Framework, não um modelo de domínio. Mudar para UUID quebraria compatibilidade com o sistema de autenticação e Sanctum.

---

## Colunas

| Coluna | Tipo BD | Tipo PHP | Notas |
|---|---|---|---|
| `id` | `bigint` PK autoincrement | `int` | Excepção — não UUID |
| `name` | `string(255)` | `string` | |
| `email` | `string(255)` unique | `string` | |
| `email_verified_at` | `timestamp` nullable | `Carbon\|null` | |
| `password` | `string` | `string` | Cast `hashed`; hidden |
| `remember_token` | `string(100)` nullable | `string\|null` | Hidden |
| `created_at` | `timestamp` | `Carbon` | |
| `updated_at` | `timestamp` | `Carbon` | |

---

## Traits e atributos

- `HasApiTokens` — Sanctum; emissão e revogação de tokens Bearer
- `HasFactory` — `UserFactory`
- `HasRoles` — Spatie Laravel Permission; gestão de roles e permissions
- `#[Fillable(['name', 'email', 'password'])]`
- `#[Hidden(['password', 'remember_token'])]`
- `casts()`: `email_verified_at` → `datetime`; `password` → `hashed`

---

## Relações

| Relação | Tipo | Modelo | Via |
|---|---|---|---|
| `tokens` | `HasMany` | `PersonalAccessToken` | `HasApiTokens` |
| `roles` | `BelongsToMany` | `Spatie\Permission\Models\Role` | `HasRoles` |
| `permissions` | `BelongsToMany` | `Spatie\Permission\Models\Permission` | `HasRoles` |

---

## Autorização (Roles e Permissions)

Guard: `web` (único guard configurado em `config/auth.php`; Sanctum autentica via middleware, não regista guard separado).

**Roles disponíveis:**

| Role | Permissions |
|---|---|
| `admin` | todas (`entidades.*`, `categorias-documento.*`) |
| `utilizador` | `entidades.ver`, `categorias-documento.ver` |

**Permissions disponíveis:**

| Permission | Descrição |
|---|---|
| `entidades.ver` | Listar e ver entidades |
| `entidades.criar` | Criar entidade |
| `entidades.actualizar` | Actualizar entidade |
| `entidades.eliminar` | Eliminar entidade |
| `categorias-documento.ver` | Listar e ver categorias |
| `categorias-documento.criar` | Criar categoria |
| `categorias-documento.actualizar` | Actualizar categoria |
| `categorias-documento.eliminar` | Eliminar categoria |

Roles e permissions são criados por **data migration** (`database/migrations/2026_06_22_150715_seed_roles_and_permissions.php`) — correm automaticamente com `php artisan migrate` em todos os ambientes.

> Detalhe completo: `04-infra/autorizacao.md`

---

## Notas Sanctum

- `createToken(string $name, array $abilities = ['*']): NewAccessToken`
- `currentAccessToken(): TransientToken|PersonalAccessToken` — devolve o token que autenticou o pedido actual
- `tokens()->delete()` — revoga todos os tokens
- `currentAccessToken()->delete()` — revoga apenas o token actual (logout)
- Tokens armazenados em hash SHA-256 na tabela `personal_access_tokens`
- TTL configurável via `SANCTUM_TOKEN_EXPIRATION` (minutos); `525600` = 1 ano
