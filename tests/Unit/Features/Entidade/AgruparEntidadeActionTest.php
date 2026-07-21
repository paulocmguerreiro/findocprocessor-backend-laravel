<?php

declare(strict_types=1);

use App\Features\Entidade\Agrupar\AgrupamentoInvalidoException;
use App\Features\Entidade\Agrupar\AgruparEntidadeAction;
use App\Features\Entidade\Agrupar\InventarioReferenciasEntidadeInterface;
use App\Models\Documento;
use App\Models\Entidade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['entidades'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('reponta os documentos da secundária para a principal', function (): void {
        $principal = Entidade::factory()->create();
        $secundaria = Entidade::factory()->create();
        $documentoFornecedor = Documento::factory()->create(['id_fornecedor' => $secundaria->id]);
        $documentoCliente = Documento::factory()->create(['id_cliente' => $secundaria->id]);

        app(AgruparEntidadeAction::class)->handle($principal, $secundaria);

        $this->assertDatabaseHas('documentos', ['id' => $documentoFornecedor->id, 'id_fornecedor' => $principal->id]);
        $this->assertDatabaseHas('documentos', ['id' => $documentoCliente->id, 'id_cliente' => $principal->id]);
    });

    it('remove permanentemente a secundária após repontar (hard-delete)', function (): void {
        $principal = Entidade::factory()->create();
        $secundaria = Entidade::factory()->create();
        Documento::factory()->create(['id_fornecedor' => $secundaria->id]);

        app(AgruparEntidadeAction::class)->handle($principal, $secundaria);

        $this->assertDatabaseMissing('entidades', ['id' => $secundaria->id]);
        expect(Entidade::withTrashed()->find($secundaria->id))->toBeNull();
    });

    it('aceita UUIDs em string e devolve a principal', function (): void {
        $principal = Entidade::factory()->create();
        $secundaria = Entidade::factory()->create();

        $resultado = app(AgruparEntidadeAction::class)->handle($principal->id, $secundaria->id);

        expect($resultado)->toBeInstanceOf(Entidade::class)
            ->and($resultado->id)->toBe($principal->id);
    });

    it('une os papéis por OR mantendo e_empresa_aplicacao da principal', function (): void {
        $principal = Entidade::factory()->create([
            'e_cliente' => false,
            'e_fornecedor' => true,
            'e_empresa_aplicacao' => true,
        ]);
        $secundaria = Entidade::factory()->create([
            'e_cliente' => true,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => false,
        ]);

        $resultado = app(AgruparEntidadeAction::class)->handle($principal, $secundaria);

        expect($resultado->e_cliente)->toBeTrue()
            ->and($resultado->e_fornecedor)->toBeTrue()
            ->and($resultado->e_empresa_aplicacao)->toBeTrue();
    });

    it('lança excepção sem mutar quando principal e secundária são iguais', function (): void {
        $entidade = Entidade::factory()->create();

        expect(fn (): Entidade => app(AgruparEntidadeAction::class)->handle($entidade, $entidade))
            ->toThrow(AgrupamentoInvalidoException::class);

        $this->assertDatabaseHas('entidades', ['id' => $entidade->id]);
    });

    it('lança excepção sem mutar quando a secundária é a empresa aplicação', function (): void {
        $principal = Entidade::factory()->create();
        $secundaria = Entidade::factory()->empresaAplicacao()->create();

        expect(fn (): Entidade => app(AgruparEntidadeAction::class)->handle($principal, $secundaria))
            ->toThrow(AgrupamentoInvalidoException::class);

        $this->assertDatabaseHas('entidades', ['id' => $secundaria->id]);
    });

    it('falha e faz rollback quando o inventário detecta uma FK não tratada', function (): void {
        // Substitui a introspecção por uma que reporta uma coluna fora da allow-list (CA-08),
        // sem manipular o esquema real — compatível com testes em paralelo sobre BD partilhada.
        $this->app->bind(InventarioReferenciasEntidadeInterface::class, fn (): InventarioReferenciasEntidadeInterface => new class implements InventarioReferenciasEntidadeInterface
        {
            public function detectarColunasQueReferenciamEntidades(): array
            {
                return ['documentos.id_fornecedor', 'documentos.id_cliente', 'faturas.id_entidade'];
            }
        });

        $principal = Entidade::factory()->create();
        $secundaria = Entidade::factory()->create();
        Documento::factory()->create(['id_fornecedor' => $secundaria->id]);

        expect(fn (): Entidade => app(AgruparEntidadeAction::class)->handle($principal, $secundaria))
            ->toThrow(AgrupamentoInvalidoException::class);

        // Rollback total: a secundária não é removida e o documento continua a apontar-lhe.
        $this->assertDatabaseHas('entidades', ['id' => $secundaria->id]);
        $this->assertDatabaseHas('documentos', ['id_fornecedor' => $secundaria->id]);
    });
});

describe('sem permissão de agrupar', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException', function (): void {
        $principal = Entidade::factory()->create();
        $secundaria = Entidade::factory()->create();

        expect(fn (): Entidade => app(AgruparEntidadeAction::class)->handle($principal, $secundaria))
            ->toThrow(AuthorizationException::class);
    });
});
