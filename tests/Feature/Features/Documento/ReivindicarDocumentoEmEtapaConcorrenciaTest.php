<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Concorrência real (duas conexões MySQL) da reivindicação por lease das etapas de
 * análise (RF-08/RNF-01): prova que dois workers não reclamam o mesmo `Documento`.
 * Enquanto A segura o lock da linha candidata, B (com `innodb_lock_wait_timeout=1`)
 * não a consegue bloquear; depois de A gravar o lease e commitar, B relê e o lease
 * fresco exclui o documento (`whereDoesntHave`).
 *
 * Reutiliza o padrão de duas conexões clonadas em runtime de
 * `ReivindicarDocumentoPendenteConcorrenciaTest` (#90) — ver esse ficheiro para o
 * porquê de nenhuma delas ser a conexão `mysql` embrulhada pelo `RefreshDatabase`.
 */
it('impede dois workers de reclamarem o mesmo documento numa etapa de análise', function (): void {
    config([
        'database.connections.mysql_etapa_a' => config('database.connections.mysql'),
        'database.connections.mysql_etapa_b' => config('database.connections.mysql'),
    ]);
    DB::purge('mysql_etapa_a');
    DB::purge('mysql_etapa_b');

    $conexaoA = DB::connection('mysql_etapa_a');
    $conexaoB = DB::connection('mysql_etapa_b');

    $documento = Documento::factory()->analiseTexto()->make(['id_responsavel' => null]);
    $documento->setConnection('mysql_etapa_a')->save();

    $leaseExpiraAntesDe = now()->subSeconds(config()->integer('extracao.ttl_lease'));

    // Selecção de candidato idêntica à da Action: estado + sem lease fresco, sob lock.
    $candidatoSobLock = fn (Connection $conexao) => $conexao->table('documentos')
        ->where('estado', EstadoDocumento::AnaliseTexto->value)
        ->whereNotExists(function (Builder $sub) use ($leaseExpiraAntesDe): void {
            $sub->select(DB::raw('1'))
                ->from('extracoes_documento')
                ->whereColumn('extracoes_documento.id_documento', 'documentos.id')
                ->where('extracao_reclamada_em', '>=', $leaseExpiraAntesDe);
        })
        ->lockForUpdate();

    try {
        $conexaoA->beginTransaction();

        expect($candidatoSobLock($conexaoA)->first())->not->toBeNull();

        // A reivindica: grava o lease (ainda sem commit).
        $conexaoA->table('extracoes_documento')->insert([
            'id' => (string) Str::uuid(),
            'id_documento' => $documento->id,
            'extracao_reclamada_em' => now(),
            'extracao_tentativas' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // B não consegue bloquear a mesma linha enquanto A segura o lock.
        $conexaoB->statement('SET SESSION innodb_lock_wait_timeout = 1');
        expect(fn () => $candidatoSobLock($conexaoB)->first())->toThrow(QueryException::class);

        $conexaoA->commit();

        // Depois do commit de A, o lease fresco exclui o documento para B.
        expect($candidatoSobLock($conexaoB)->first())->toBeNull();
    } finally {
        $conexaoB->table('extracoes_documento')->where('id_documento', $documento->id)->delete();
        $conexaoB->table('documentos')->where('id', $documento->id)->delete();
    }
});
