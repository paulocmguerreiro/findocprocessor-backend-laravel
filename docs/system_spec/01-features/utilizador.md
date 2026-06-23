# System Spec — Feature: Utilizador

> `App\Features\Utilizador\`
> Issue #50

Gestão de utilizadores — actualmente apenas o caso de uso de atribuição de role. O modelo `User` é partilhado com a feature Auth.

**Fluxo de dados:**
```
HTTP Request → AtribuirRoleRequest (autoriza + valida) → UtilizadorController → AtribuirRoleAction (autoriza + syncRoles) → ApiResponse 204
```

**Autorização:** Policy `UtilizadorPolicy` registada via `#[UsePolicy(UtilizadorPolicy::class)]` no modelo `User`.

---

## Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `AtribuirRoleAction` | `App\Features\Utilizador\AtribuirRole` | `handle(User $utilizador, string $nomeRole): User` | Substitui role do utilizador via `syncRoles()`; lança `DomainException` se auto-modificação |

---

## Invariante de domínio — auto-modificação de role

`AtribuirRoleAction` impede que um utilizador altere o próprio role, mesmo com a permission `utilizadores.atribuir-role`:

```php
if (auth()->id() === $utilizador->id) {
    throw new \DomainException('Não é possível alterar o próprio role.');
}
```

**Motivo:** prevenir auto-bloqueio acidental (ex: director a remover o próprio role de admin). O guard é aplicado na Action (não na Policy) para cobrir todos os contextos de invocação (HTTP, Job, Artisan).

Handler converte `DomainException` → 422 em contexto HTTP (`bootstrap/app.php`).

---

## Policy

`UtilizadorPolicy` (`app/Policies/UtilizadorPolicy.php`):

```php
public function atribuirRole(User $utilizador, User $alvo): bool
{
    return $utilizador->hasPermissionTo('utilizadores.atribuir-role');
}
```

Registada via `#[UsePolicy(UtilizadorPolicy::class)]` no modelo `User` (`app/Models/User.php`).
