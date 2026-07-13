<?php

declare(strict_types=1);

use App\Models\Documento;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Primeiro teste de concorrência real do projecto — prova que `lockForUpdate()`
 * cria exclusão mútua entre duas conexões MySQL distintas (dois "workers").
 *
 * A conexão de teste por omissão (`mysql`) é embrulhada numa transacção pelo
 * `RefreshDatabase` (nunca commitada de facto — `connectionsToTransact()` só
 * inclui `database.default`, ver `Illuminate\Foundation\Testing\RefreshDatabase`).
 * Por isso este teste usa **duas conexões adicionais**, clonadas em runtime a
 * partir da config `mysql` (mesma BD de teste, incl. sufixo `_test_N` do
 * paralelo), nenhuma delas `mysql` — só assim `commit()` liberta o lock de
 * facto ao nível do motor. O `Documento` é criado directamente na conexão A
 * (bypassa o wrapper), por isso a limpeza no fim do teste é manual.
 */
it('impede duas conexões concorrentes de bloquearem a mesma linha em simultâneo (lockForUpdate)', function (): void {
    config([
        'database.connections.mysql_teste_concorrente_a' => config('database.connections.mysql'),
        'database.connections.mysql_teste_concorrente_b' => config('database.connections.mysql'),
    ]);
    DB::purge('mysql_teste_concorrente_a');
    DB::purge('mysql_teste_concorrente_b');

    $conexaoA = DB::connection('mysql_teste_concorrente_a');
    $conexaoB = DB::connection('mysql_teste_concorrente_b');

    // `id_responsavel` fica a null: `User::factory()` resolveria na conexão
    // por omissão (embrulhada, nunca commitada), bloqueando o FK check desta
    // INSERT (lock wait timeout à espera de uma linha invisível a esta conexão).
    $documento = Documento::factory()->pendente()->make(['id_responsavel' => null]);
    $documento->setConnection('mysql_teste_concorrente_a')->save();

    try {
        $conexaoA->beginTransaction();
        $conexaoA->table('documentos')->where('id', $documento->id)->lockForUpdate()->first();

        $conexaoB->statement('SET SESSION innodb_lock_wait_timeout = 1');

        expect(fn () => $conexaoB->table('documentos')->where('id', $documento->id)->lockForUpdate()->first())
            ->toThrow(QueryException::class);

        $conexaoA->commit();

        $resultado = $conexaoB->table('documentos')->where('id', $documento->id)->lockForUpdate()->first();

        expect($resultado)->not->toBeNull();
    } finally {
        $conexaoB->table('documentos')->where('id', $documento->id)->delete();
    }
});
