<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltravelPorEstadoRegisto;
use App\Models\Concerns\RegistaActividade;
use App\Policies\UtilizadorPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 * @property-read Carbon|null $email_verified_at
 * @property-read string $password
 * @property-read string|null $remember_token
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Carbon|null $deleted_at
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Permission> $permissions
 */
#[Table('users')]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UsePolicy(UtilizadorPolicy::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use FiltravelPorEstadoRegisto, HasApiTokens, HasFactory, HasRoles, Notifiable, RegistaActividade, SoftDeletes;

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return list<string>
     */
    protected function atributosExcluidosDaActividade(): array
    {
        return ['password', 'remember_token'];
    }
}
