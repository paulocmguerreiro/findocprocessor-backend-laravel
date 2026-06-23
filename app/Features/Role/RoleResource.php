<?php

declare(strict_types=1);

namespace App\Features\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
final class RoleResource extends JsonResource
{
    /**
     * @return array{id: int, nome: string, permissoes: array<int, string>}
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->name,
            'permissoes' => $this->permissions->pluck('name')->sort()->values()->all(),
        ];
    }
}
