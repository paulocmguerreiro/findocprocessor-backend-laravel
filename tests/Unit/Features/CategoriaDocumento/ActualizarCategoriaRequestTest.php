<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Models\CategoriaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

function requestComCategoria(string $uuid): ActualizarCategoriaRequest
{
    $request = new ActualizarCategoriaRequest;
    $request->setRouteResolver(fn (): object => new readonly class($uuid)
    {
        public function __construct(private string $uuid) {}

        public function parameter(string $name, mixed $default = null): mixed
        {
            return $name === 'categoria' ? $this->uuid : $default;
        }
    });

    return $request;
}

describe('ActualizarCategoriaRequest — autorização e regras sem BD', function (): void {
    it('authorize retorna true', function (): void {
        expect((new ActualizarCategoriaRequest)->authorize())->toBeTrue();
    });

    it('aceita payload só com nome', function (): void {
        $request = requestComCategoria('qualquer-uuid');
        $validator = Validator::make(
            ['nome' => 'Novo Nome'],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->fails())->toBeFalse();
    });

    it('aceita payload vazio', function (): void {
        $request = requestComCategoria('qualquer-uuid');
        $validator = Validator::make(
            [],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->fails())->toBeFalse();
    });

    it('rejeita tipo_movimento inválido', function (): void {
        $request = requestComCategoria('qualquer-uuid');
        $validator = Validator::make(
            ['tipo_movimento' => 'invalido'],
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
            ['slug' => 'fatura'],
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
            ['slug' => 'fatura'],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('slug'))
            ->toBe('Já existe uma Categoria com este identificador da URL.');
    });
});
