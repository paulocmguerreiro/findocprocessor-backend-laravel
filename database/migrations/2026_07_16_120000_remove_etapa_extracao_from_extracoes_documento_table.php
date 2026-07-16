<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extracoes_documento', function (Blueprint $table): void {
            // A coluna faz parte do índice composto — largar o índice antes da coluna.
            $table->dropIndex(['etapa_extracao', 'extracao_reclamada_em']);
            $table->dropColumn('etapa_extracao');

            // O estado unificado (documentos.estado) exprime a etapa; o lease mantém-se
            // como único critério de varredura do orquestrador (#101).
            $table->index('extracao_reclamada_em');
        });
    }

    public function down(): void
    {
        Schema::table('extracoes_documento', function (Blueprint $table): void {
            $table->dropIndex(['extracao_reclamada_em']);
            $table->string('etapa_extracao', 50)->default('PENDENTE')->after('id_documento')
                ->comment('Etapa actual da extracao; cast EtapaExtracao');
            $table->index(['etapa_extracao', 'extracao_reclamada_em']);
        });
    }
};
