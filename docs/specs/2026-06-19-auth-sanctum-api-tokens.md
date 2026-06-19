# Spec — Issue #35: feat(auth): autenticação via Laravel Sanctum

**Issue:** #35
**Data:** 2026-06-19
**Slug:** auth-sanctum-api-tokens
**Brief:** `docs/briefs/2026-06-19-auth-sanctum-api-tokens.md`

---

## Contratos por camada

### 1. Instalação Sanctum

```bash
php artisan install:api
# Publica: config/sanctum.php + migration create_personal_access_tokens_table
php artisan migrate
```

`.env.example` — adicionar:
```
SANCTUM_TOKEN_EXPIRATION=525600  # 1 ano em minutos (ajustável por ambiente)
```

---

### 2. Model User — alterações

**Ficheiro:** `app/Models/User.php`

```php
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 * @property-read Carbon|null $email_verified_at
 * @property-read string $password
 * @property-read string|null $remember_token
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PersonalAccessToken> $tokens
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    // ...
}
```

> **Nota:** `User` mantém PK `int $id` — excepção intencional documentada. Não é modelo de domínio; é o modelo de autenticação Laravel. Não aplicar `HasUuids`.

---

### 3. Feature slice Auth

#### Estrutura de ficheiros

```
app/Features/Auth/
  AuthController.php
  Login/
    LoginAction.php
    LoginRequest.php
    LoginResource.php
  Logout/
    LogoutAction.php
  CriarToken/
    CriarTokenAction.php
    CriarTokenRequest.php
    CriarTokenResource.php
```

#### 3.1 LoginRequest

```php
// app/Features/Auth/Login/LoginRequest.php
final class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }  // acção pública

    /** @return array<string, list<string|\Illuminate\Contracts\Validation\Rule>> */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required'    => 'O email é obrigatório.',
            'email.email'       => 'O email não tem formato válido.',
            'password.required' => 'A password é obrigatória.',
        ];
    }
}
```

#### 3.2 LoginAction

```php
// app/Features/Auth/Login/LoginAction.php
final class LoginAction
{
    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(string $email, string $password): string
    {
        // Sem Gate::authorize() — acção pública

        return DB::transaction(function () use ($email, $password): string {
            $utilizador = User::where('email', $email)->first();

            if (! $utilizador || ! Hash::check($password, $utilizador->password)) {
                throw ValidationException::withMessages([
                    'email' => ['As credenciais fornecidas estão incorrectas.'],
                ]);
            }

            return $utilizador->createToken('api', ['api'])->plainTextToken;
        });
    }
}
```

> `ValidationException` lançada dentro da transação: a transação faz rollback limpo (sem escrita). Credenciais erradas nunca chegam a `createToken`.

#### 3.3 LoginResource

```php
// app/Features/Auth/Login/LoginResource.php
final class LoginResource extends JsonResource
{
    /** @return array{token: string} */
    public function toArray(Request $request): array
    {
        return ['token' => $this->resource];
    }
}
```

#### 3.4 LogoutAction

```php
// app/Features/Auth/Logout/LogoutAction.php
final class LogoutAction
{
    /**
     * @throws \Throwable
     */
    public function handle(User $utilizador): void
    {
        // Sem Gate::authorize() — utilizador revoga o seu próprio token
        // middleware auth:sanctum já garante autenticação

        DB::transaction(function () use ($utilizador): void {
            $utilizador->currentAccessToken()->delete();
        });
    }
}
```

#### 3.5 CriarTokenRequest

```php
// app/Features/Auth/CriarToken/CriarTokenRequest.php
final class CriarTokenRequest extends FormRequest
{
    public function authorize(): bool { return true; }  // middleware auth:sanctum protege

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'nome_token' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nome_token.required' => 'O nome do token é obrigatório.',
            'nome_token.max'      => 'O nome do token não pode exceder 255 caracteres.',
        ];
    }
}
```

#### 3.6 CriarTokenAction

```php
// app/Features/Auth/CriarToken/CriarTokenAction.php
final class CriarTokenAction
{
    /**
     * @throws \Throwable
     */
    public function handle(User $utilizador, string $nomeToken): string
    {
        // Sem Gate::authorize() — utilizador cria token para si próprio
        // middleware auth:sanctum já garante autenticação

        return DB::transaction(
            fn (): string => $utilizador->createToken($nomeToken, ['api'])->plainTextToken
        );
    }
}
```

#### 3.7 CriarTokenResource

```php
// app/Features/Auth/CriarToken/CriarTokenResource.php
final class CriarTokenResource extends JsonResource
{
    /** @return array{token: string} */
    public function toArray(Request $request): array
    {
        return ['token' => $this->resource];
    }
}
```

#### 3.8 AuthController

```php
// app/Features/Auth/AuthController.php
final class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): LoginResource
    {
        /** @var array{email: string, password: string} $dados */
        $dados = $request->validated();

        return new LoginResource($action->handle($dados['email'], $dados['password']));
    }

    public function logout(Request $request, LogoutAction $action): Response
    {
        /** @var User $utilizador */
        $utilizador = $request->user();
        $action->handle($utilizador);

        return response()->noContent();
    }

    public function criarToken(CriarTokenRequest $request, CriarTokenAction $action): CriarTokenResource
    {
        /** @var array{nome_token: string} $dados */
        $dados = $request->validated();

        /** @var User $utilizador */
        $utilizador = $request->user();

        return new CriarTokenResource($action->handle($utilizador, $dados['nome_token']));
    }
}
```

---

### 4. Rotas

**Ficheiro:** `routes/api.php`

```php
// Públicas
Route::post('auth/login', [AuthController::class, 'login']);

// Protegidas — auth:sanctum
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/tokens', [AuthController::class, 'criarToken']);

    Route::apiResource('categorias-documento', CategoriaDocumentoController::class);
    Route::apiResource('entidades', EntidadeController::class);
    Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae']);
});
```

---

### 5. openapi.yaml — alterações

- Adicionar `securitySchemes.bearerAuth` (type: http, scheme: bearer, bearerFormat: JWT)
- Adicionar `security: [{ bearerAuth: [] }]` em todas as rotas protegidas
- Manter `POST /auth/login` sem `security` (pública)
- Novo path `POST /auth/logout`
- Novo path `POST /auth/tokens`

---

### 6. Testes

#### Padrão dual — estrutura

```
tests/Unit/Features/Auth/
  LoginActionTest.php
  LogoutActionTest.php
  CriarTokenActionTest.php

tests/Feature/Features/Auth/
  LoginTest.php
  LogoutTest.php
  CriarTokenTest.php
```

#### Cenários obrigatórios

**LoginActionTest (Unit)**
- Login com credenciais correctas → retorna string de token
- Login com email errado → `ValidationException`
- Login com password errada → `ValidationException`

**LoginTest (Feature/HTTP)**
- `POST /auth/login` com credenciais válidas → 200 + `{ token: "..." }`
- `POST /auth/login` com credenciais inválidas → 422
- `POST /auth/login` sem campos → 422

**LogoutTest (Feature/HTTP)**
- `POST /auth/logout` sem token → 401
- `POST /auth/logout` com token válido → 204 + token revogado

**CriarTokenTest (Feature/HTTP)**
- `POST /auth/tokens` sem autenticação → 401
- `POST /auth/tokens` com autenticação → 200 + novo token

**Acesso protegido (Feature/HTTP) — regressão**
- `GET /categorias-documento` sem token → 401
- `GET /categorias-documento` com token → 200

#### Helper de autenticação em testes

```php
// Usando Sanctum::actingAs()
Sanctum::actingAs(User::factory()->create(), ['api']);
```

---

## SYSTEM_SPEC a actualizar

| Ficheiro | Alteração |
|---|---|
| `00-index.md` | Adicionar `Auth` em features implementadas + `05-routes/auth.md` |
| `01-features/auth.md` | Criar — descrever LoginAction, LogoutAction, CriarTokenAction |
| `05-routes/auth.md` | Criar — rotas auth |
| `03-models/user.md` | Criar — documentar User + HasApiTokens |

---

## Fora de âmbito

- OAuth2, Refresh tokens, 2FA
- Autorização por roles/permissions (#35 faz referência a issue futura)
- Rate limiting no endpoint de login (issue futura)
