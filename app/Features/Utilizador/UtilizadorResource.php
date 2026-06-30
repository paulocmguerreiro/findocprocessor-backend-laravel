<?php

declare(strict_types=1);

namespace App\Features\Utilizador;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UtilizadorResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, email: string, roles: array<int, mixed>, deleted_at: ?string, created_at: string}
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles->pluck('name')->values()->all(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
