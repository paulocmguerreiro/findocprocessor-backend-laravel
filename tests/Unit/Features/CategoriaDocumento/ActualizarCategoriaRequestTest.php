<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

function requestComCategoria(string $uuid): ActualizarCategoriaRequest
{
    $request = new ActualizarCategoriaRequest;
    $request->setRouteResolver(fn (): object => new readonly class($uuid)
    {
        public function __construct(private string $uuid) {}

        public function parameter(string $name, mixed $default = null): mixed
        {
            return $name === 'categorias_documento' ? $this->uuid : $default;
        }
    });

    return $request;
}

function payloadCompleto(array $sobrepor = []): array
{
    return array_merge([
        'nome' => 'Nome Válido',
        'slug' => 'nome-valido',
        'tipo_movimento' => TipoMovimento::Neutro->value,
    ], $sobrepor);
}

describe('ActualizarCategoriaRequest — autorização e regras sem BD', function (): void {
    it('authorize delega em Gate::authorize com update', function (): void {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('update', null);

        expect((new ActualizarCategoriaRequest)->authorize())->toBeTrue();
    });

    it('aceita payload com todos os campos válidos', function (): void {
        $request = requestComCategoria('qualquer-uuid');
        $validator = Validator::make(
            payloadCompleto(),
            $request->rules(),
            $request->messages(),
        );

        expect($validator->fails())->toBeFalse();
    });

    it('rejeita payload sem nome', function (): void {
        $request = requestComCategoria('qualquer-uuid');
        $validator = Validator::make(
            payloadCompleto(['nome' => null]),
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('nome'))
            ->toBe('O nome da Categoria é obrigatório.');
    });

    it('rejeita payload sem slug', function (): void {
        $request = requestComCategoria('qualquer-uuid');
        $validator = Validator::make(
            payloadCompleto(['slug' => null]),
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('slug'))
            ->toBe('O identificador da URL da Categoria é obrigatório.');
    });

    it('rejeita payload sem tipo_movimento', function (): void {
        $request = requestComCategoria('qualquer-uuid');
        $validator = Validator::make(
            payloadCompleto(['tipo_movimento' => null]),
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('tipo_movimento'))
            ->toBe('O tipo de movimento é obrigatório.');
    });

    it('rejeita tipo_movimento inválido', function (): void {
        $request = requestComCategoria('qualquer-uuid');
        $validator = Validator::make(
            payloadCompleto(['tipo_movimento' => 'invalido']),
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('tipo_movimento'))
            ->toBe('O tipo de movimento indicado não é válido.');
    });
});

describe('ActualizarCategoriaRequest — unicidade (BD)', function (): void {
    uses(RefreshDatabase::class);

    it('aceita slug igual ao registo actual', function (): void {
        $categoria = CategoriaDocumento::factory()->create(['slug' => 'fatura']);

        $request = requestComCategoria($categoria->id);
        $validator = Validator::make(
            payloadCompleto(['slug' => 'fatura']),
            $request->rules(),
            $request->messages(),
        );

        expect($validator->fails())->toBeFalse();
    });

    it('rejeita slug de outro registo existente', function (): void {
        CategoriaDocumento::factory()->create(['slug' => 'fatura']);
        $outra = CategoriaDocumento::factory()->create(['slug' => 'recibo']);

        $request = requestComCategoria($outra->id);
        $validator = Validator::make(
            payloadCompleto(['slug' => 'fatura']),
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('slug'))
            ->toBe('Já existe uma Categoria com este identificador da URL.');
    });
});
