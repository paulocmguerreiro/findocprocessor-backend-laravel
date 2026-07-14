<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extracoes_documento', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador unico UUID v7');

            $table->foreignUuid('id_documento')->unique()->constrained('documentos')
                ->cascadeOnDelete()->cascadeOnUpdate()->comment('FK para o documento; 1-1, dimensao de extracao segue o documento');

            $table->string('etapa_extracao', 50)->default('PENDENTE')->comment('Etapa actual da extracao; cast EtapaExtracao');
            $table->timestamp('extracao_reclamada_em')->nullable()->comment('Lease de reivindicacao pelo orquestrador; TTL em config(extracao.ttl_lease)');
            $table->unsignedTinyInteger('extracao_tentativas')->default(0)->comment('Contador de tentativas; tecto em config(extracao.max_tentativas)');
            $table->longText('texto_extraido')->nullable()->comment('Texto extraido do documento; PII, nunca em Resource');
            $table->json('dados_json')->nullable()->comment('Dados estruturados extraidos; PII, nunca em Resource');

            $table->timestamps();

            $table->index(['etapa_extracao', 'extracao_reclamada_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracoes_documento');
    }
};
