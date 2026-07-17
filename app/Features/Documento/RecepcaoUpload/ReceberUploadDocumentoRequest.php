<?php

declare(strict_types=1);

namespace App\Features\Documento\RecepcaoUpload;

use App\Models\Documento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ReceberUploadDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('create', Documento::class);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ficheiro' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/tiff,image/bmp,image/webp', 'max:51200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'ficheiro.required' => 'O ficheiro é obrigatório.',
            'ficheiro.file' => 'O ficheiro enviado é inválido.',
            'ficheiro.mimetypes' => 'O ficheiro tem de ser PDF, JPG, JPEG, PNG, TIFF, BMP ou WEBP.',
            'ficheiro.max' => 'O ficheiro não pode exceder 50 MB.',
        ];
    }
}
