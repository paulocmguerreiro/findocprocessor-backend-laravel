<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapas_documento', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Identificador unico UUID v7');

            $table->foreignUuid('id_documento')->constrained('documentos')
                ->cascadeOnDelete()->comment('FK para o documento; historico segue o documento');

            $table->string('estado', 50)->index()->comment('Etapa atingida; cast EstadoDocumento');
            $table->text('motivo')->nullable()->comment('Motivo/resposta/nota da etapa; pode ser sensivel');

            $table->foreignId('id_utilizador')->nullable()->constrained('users')
                ->nullOnDelete()->comment('FK para o utilizador; null = passo automatico (sistema)');

            $table->timestamp('created_at')->nullable()->comment('Data+hora da etapa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etapas_documento');
    }
};
