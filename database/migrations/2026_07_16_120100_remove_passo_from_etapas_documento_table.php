<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etapas_documento', function (Blueprint $table): void {
            // Redundante com o `estado` unificado da própria linha de histórico;
            // `resultado` mantém-se (distingue tentativa de passo de transição de negócio).
            $table->dropColumn('passo');
        });
    }

    public function down(): void
    {
        Schema::table('etapas_documento', function (Blueprint $table): void {
            $table->string('passo', 50)->nullable()->after('estado')
                ->comment('Passo de IA da extracao; cast EtapaExtracao; null = linha de negocio');
        });
    }
};
