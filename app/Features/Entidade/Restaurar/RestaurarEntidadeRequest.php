<?php

declare(strict_types=1);

namespace App\Features\Entidade\Restaurar;

use App\Models\Entidade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RestaurarEntidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var string $idEntidade */
        $idEntidade = $this->route('entidade');
        $entidade = Entidade::withTrashed()->findOrFail($idEntidade);

        Gate::authorize('restore', $entidade);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
