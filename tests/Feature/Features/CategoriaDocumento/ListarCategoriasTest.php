<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;

uses(RefreshDatabase::class);

it('devolve lista vazia quando não existem categorias', function (): void {
    $this->getJson('/api/categorias-documento')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('links.next', null)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonStructure([
            'data',
            'links' => ['prev', 'next'],
            'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
        ]);
});

it('devolve lista de categorias com estrutura correcta', function (): void {
    CategoriaDocumento::factory()->count(3)->create();

    $this->getJson('/api/categorias-documento')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
            'links' => ['prev', 'next'],
            'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
        ]);
});

it('respeita o parâmetro per_page na paginação', function (): void {
    CategoriaDocumento::factory()->count(5)->create();

    $resposta = $this->getJson('/api/categorias-documento?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonStructure([
            'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
            'links' => ['prev', 'next'],
            'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
        ]);

    expect($resposta->json('links.next'))->not->toBeNull();
});

it('navega para a página seguinte via cursor sem duplicados', function (): void {
    CategoriaDocumento::factory()->count(5)->create();

    $pagina1 = $this->getJson('/api/categorias-documento?per_page=2&sort=nome')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
            'links' => ['prev', 'next'],
            'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
        ]);
    $idsPagina1 = $pagina1->json('data.*.id');

    $nextUrl = $pagina1->json('links.next');
    expect($nextUrl)->not->toBeNull();

    parse_str((string) parse_url((string) $nextUrl, PHP_URL_QUERY), $params);
    $pagina2 = $this->getJson('/api/categorias-documento?per_page=2&sort=nome&cursor='.$params['cursor'])
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
            'links' => ['prev', 'next'],
            'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
        ]);

    expect(array_intersect($idsPagina1, $pagina2->json('data.*.id')))->toBeEmpty();
});

it('rejeita per_page acima do máximo com erro de validação', function (): void {
    $this->getJson('/api/categorias-documento?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});

it('rejeita sort inválido com erro de validação', function (): void {
    $this->getJson('/api/categorias-documento?sort=invalido')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);
});

it('rejeita direction inválida com erro de validação', function (): void {
    $this->getJson('/api/categorias-documento?direction=invalido')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['direction']);
});

it('cursor além do fim devolve lista vazia', function (): void {
    CategoriaDocumento::factory()->count(3)->create();

    $cursor = new Cursor(['nome' => str_repeat('z', 255)], true);

    $this->getJson('/api/categorias-documento?sort=nome&cursor='.$cursor->encode())
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('links.next', null)
        ->assertJsonStructure([
            'data',
            'links' => ['prev', 'next'],
            'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
        ]);
});

it('guest pode listar categorias', function (): void {
    $this->getJson('/api/categorias-documento')
        ->assertOk();
});
