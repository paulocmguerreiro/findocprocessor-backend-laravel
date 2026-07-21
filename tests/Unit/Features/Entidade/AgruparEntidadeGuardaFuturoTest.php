<?php

declare(strict_types=1);

use App\Features\Entidade\Agrupar\AgrupamentoInvalidoException;
use App\Features\Entidade\Agrupar\AgruparEntidadeAction;
use App\Models\Entidade;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;

/*
 * Guarda de futuro (CA-08): usa DatabaseMigrations em vez de RefreshDatabase
 * porque cria uma tabela real com FK → entidades. O DDL do MySQL faz commit
 * implícito, o que quebraria os savepoints do RefreshDatabase; o DatabaseMigrations
 * não envolve o teste numa transação, pelo que a `DB::transaction` da Action é real.
 */
uses(DatabaseMigrations::class);

afterEach(fn () => Schema::dropIfExists('referencias_temp_entidade'));

it('falha com AgrupamentoInvalidoException e não remove a secundária quando surge uma FK a entidades não tratada', function (): void {
    $this->actingAs(criarAdmin());

    $principal = Entidade::factory()->create();
    $secundaria = Entidade::factory()->create();

    Schema::create('referencias_temp_entidade', function (Blueprint $tabela): void {
        $tabela->uuid('id')->primary();
        $tabela->foreignUuid('id_entidade')->constrained('entidades');
    });

    expect(fn (): Entidade => app(AgruparEntidadeAction::class)->handle($principal, $secundaria))
        ->toThrow(AgrupamentoInvalidoException::class);

    $this->assertDatabaseHas('entidades', ['id' => $secundaria->id]);
});
