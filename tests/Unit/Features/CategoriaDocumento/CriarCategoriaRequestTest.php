<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest;
use App\Models\CategoriaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('admin');
    $this->actingAs($utilizador);
});

describe('CriarCategoriaRequest — autorização e regras', function (): void {
    it('authorize retorna true', function (): void {
        expect((new CriarCategoriaRequest)->authorize())->toBeTrue();
    });

    it('aceita payload válido', function (): void {
        $request = new CriarCategoriaRequest;
        $validator = Validator::make(
            ['nome' => 'Fatura', 'slug' => 'fatura', 'tipo_movimento' => 'debito'],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->fails())->toBeFalse();
    });

    it('rejeita nome em falta', function (): void {
        $request = new CriarCategoriaRequest;
        $validator = Validator::make(
            ['slug' => 'fatura', 'tipo_movimento' => 'debito'],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('nome'))
            ->toBe('O nome da Categoria é obrigatório.');
    });

    it('rejeita slug em falta', function (): void {
        $request = new CriarCategoriaRequest;
        $validator = Validator::make(
            ['nome' => 'Fatura', 'tipo_movimento' => 'debito'],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('slug'))
            ->toBe('O identificador da URL da Categoria é obrigatório.');
    });

    it('rejeita tipo_movimento em falta', function (): void {
        $request = new CriarCategoriaRequest;
        $validator = Validator::make(
            ['nome' => 'Fatura', 'slug' => 'fatura'],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('tipo_movimento'))
            ->toBe('O tipo de movimento é obrigatório.');
    });

    it('rejeita tipo_movimento inválido', function (): void {
        $request = new CriarCategoriaRequest;
        $validator = Validator::make(
            ['nome' => 'Fatura', 'slug' => 'fatura', 'tipo_movimento' => 'invalido'],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('tipo_movimento'))
            ->toBe('O tipo de movimento indicado não é válido.');
    });
});

describe('CriarCategoriaRequest — unicidade (BD)', function (): void {
    it('rejeita slug duplicado', function (): void {
        CategoriaDocumento::factory()->create(['slug' => 'fatura']);

        $request = new CriarCategoriaRequest;
        $validator = Validator::make(
            ['nome' => 'Fatura 2', 'slug' => 'fatura', 'tipo_movimento' => 'debito'],
            $request->rules(),
            $request->messages(),
        );

        expect($validator->errors()->first('slug'))
            ->toBe('Já existe uma Categoria com este identificador da URL.');
    });
});
