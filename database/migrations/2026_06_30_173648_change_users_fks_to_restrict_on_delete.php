<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padrão B (SoftDelete): as FKs das tabelas filhas que apontam para `users`
 * passam de nullOnDelete para restrictOnDelete. Sem restrição, o forceDelete()
 * de um utilizador anularia a autoria (id_responsavel / id_utilizador = null);
 * com restrição, um utilizador referenciado cai no fallback para soft delete,
 * preservando a integridade referencial e o histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropForeign(['id_responsavel']);
            $table->foreign('id_responsavel')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('etapas_documento', function (Blueprint $table): void {
            $table->dropForeign(['id_utilizador']);
            $table->foreign('id_utilizador')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropForeign(['id_responsavel']);
            $table->foreign('id_responsavel')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('etapas_documento', function (Blueprint $table): void {
            $table->dropForeign(['id_utilizador']);
            $table->foreign('id_utilizador')->references('id')->on('users')->nullOnDelete();
        });
    }
};
