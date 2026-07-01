<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garante que os FKs documentos.id_fornecedor/id_cliente para entidades usam
 * ON DELETE RESTRICT em todos os drivers, incluindo SQLite.
 *
 * A migration #70 (2026_06_30_135904) aplicou RESTRICT apenas em MySQL/prod e
 * saltou o SQLite (usava dropForeign por nome, não suportado em SQLite). Como
 * resultado, em testes (SQLite) os FKs mantinham-se SET NULL e o ramo soft-delete
 * do Padrão B (EliminarEntidadeAction) era inatingível — o forceDelete() anulava
 * o FK e passava sem excepção.
 *
 * A forma por colunas (dropForeign(['coluna'])) faz o SQLite reconstruir a tabela,
 * pelo que RESTRICT passa a ser aplicado também em SQLite. Em MySQL a constraint já
 * era RESTRICT; esta migration limita-se a recriá-la de forma idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropForeign(['id_fornecedor']);
            $table->dropForeign(['id_cliente']);

            $table->foreign('id_fornecedor')->references('id')->on('entidades')->restrictOnDelete();
            $table->foreign('id_cliente')->references('id')->on('entidades')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropForeign(['id_fornecedor']);
            $table->dropForeign(['id_cliente']);

            $table->foreign('id_fornecedor')->references('id')->on('entidades')->nullOnDelete();
            $table->foreign('id_cliente')->references('id')->on('entidades')->nullOnDelete();
        });
    }
};
