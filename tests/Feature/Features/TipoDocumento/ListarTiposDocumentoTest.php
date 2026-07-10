<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['tipos_documento'])->flush());

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('devolve lista vazia quando não existem tipos de documento', function (): void {
        $this->getJson('/api/tipos-documento')
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

    it('devolve lista de tipos de documento com estrutura correcta, incluindo categoria', function (): void {
        TipoDocumento::factory()->count(3)->create();

        $this->getJson('/api/tipos-documento')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'nome', 'descricao', 'categoria', 'tipo_movimento', 'posicao_empresa_mae', 'espera_data_documento', 'espera_fornecedor', 'espera_cliente', 'espera_valor']],
                'links' => ['prev', 'next'],
                'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
            ]);
    });

    it('respeita o parâmetro per_page na paginação', function (): void {
        TipoDocumento::factory()->count(5)->create();

        $resposta = $this->getJson('/api/tipos-documento?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);

        expect($resposta->json('links.next'))->not->toBeNull();
    });

    it('navega para a página seguinte via cursor sem duplicados', function (): void {
        TipoDocumento::factory()->count(5)->create();

        $pagina1 = $this->getJson('/api/tipos-documento?per_page=2&sort=nome')->assertOk();
        $idsPagina1 = $pagina1->json('data.*.id');

        $nextUrl = $pagina1->json('links.next');
        expect($nextUrl)->not->toBeNull();

        parse_str((string) parse_url((string) $nextUrl, PHP_URL_QUERY), $params);
        $pagina2 = $this->getJson('/api/tipos-documento?per_page=2&sort=nome&cursor='.$params['cursor'])->assertOk();

        expect(array_intersect($idsPagina1, $pagina2->json('data.*.id')))->toBeEmpty();
    });

    it('devolve 422 com per_page acima do máximo', function (): void {
        $this->getJson('/api/tipos-documento?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    });

    it('devolve 422 com sort inválido', function (): void {
        $this->getJson('/api/tipos-documento?sort=invalido')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    });

    it('devolve 422 com id_categoria não-uuid', function (): void {
        $this->getJson('/api/tipos-documento?id_categoria=nao-e-uuid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_categoria']);
    });

    it('devolve 422 com id_categoria inexistente', function (): void {
        $this->getJson('/api/tipos-documento?id_categoria='.Str::uuid7())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_categoria']);
    });

    it('filtra por id_categoria válido', function (): void {
        $categoriaA = CategoriaDocumento::factory()->create();
        $categoriaB = CategoriaDocumento::factory()->create();
        TipoDocumento::factory()->for($categoriaA, 'categoria')->create(['nome' => 'Da Categoria A']);
        TipoDocumento::factory()->for($categoriaB, 'categoria')->create(['nome' => 'Da Categoria B']);

        $this->getJson('/api/tipos-documento?id_categoria='.$categoriaA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Da Categoria A');
    });

    it('cursor além do fim devolve lista vazia', function (): void {
        TipoDocumento::factory()->count(3)->create();

        $cursor = new Cursor(['nome' => str_repeat('z', 255)], true);

        $this->getJson('/api/tipos-documento?sort=nome&cursor='.$cursor->encode())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

it('utilizador com permissão de leitura devolve 200', function (): void {
    criarEAutenticarUtilizador();

    $this->getJson('/api/tipos-documento')
        ->assertOk();
});

it('utilizador sem permissão de leitura recebe 403', function (): void {
    criarEAutenticarSemRole();

    $this->getJson('/api/tipos-documento')
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $this->getJson('/api/tipos-documento')
        ->assertUnauthorized();
});
