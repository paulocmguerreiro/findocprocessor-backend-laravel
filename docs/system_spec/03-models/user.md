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
- `#[Fillable(['name', 'email', 'password'])]`
- `#[Hidden(['password', 'remember_token'])]`
- `casts()`: `email_verified_at` → `datetime`; `password` → `hashed`

---

## Relações

| Relação | Tipo | Modelo | Via |
|---|---|---|---|
| `tokens` | `HasMany` | `PersonalAccessToken` | `HasApiTokens` |

---

## Notas Sanctum

- `createToken(string $name, array $abilities = ['*']): NewAccessToken`
- `currentAccessToken(): TransientToken|PersonalAccessToken` — devolve o token que autenticou o pedido actual
- `tokens()->delete()` — revoga todos os tokens
- `currentAccessToken()->delete()` — revoga apenas o token actual (logout)
- Tokens armazenados em hash SHA-256 na tabela `personal_access_tokens`
- TTL configurável via `SANCTUM_TOKEN_EXPIRATION` (minutos); `525600` = 1 ano
