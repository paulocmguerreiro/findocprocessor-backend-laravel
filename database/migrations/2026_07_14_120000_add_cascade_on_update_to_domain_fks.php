<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As PKs das tabelas de domínio são UUID v7 geradas pela aplicação; sem
 * cascadeOnUpdate, uma futura reconciliação/agregação de bases de dados que
 * precise de remapear UUIDs (ex.: resolver colisões) falharia com violação
 * de FK em qualquer UPDATE à chave primária de um registo referenciado.
 * Esta migration adiciona onUpdate cascade a todas as FKs de domínio,
 * mantendo o onDelete já definido em cada uma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropForeign(['id_fornecedor']);
            $table->foreign('id_fornecedor')->references('id')->on('entidades')
                ->restrictOnDelete()->cascadeOnUpdate();

            $table->dropForeign(['id_cliente']);
            $table->foreign('id_cliente')->references('id')->on('entidades')
                ->restrictOnDelete()->cascadeOnUpdate();

            $table->dropForeign(['id_categoria']);
            $table->foreign('id_categoria')->references('id')->on('categorias_documento')
                ->restrictOnDelete()->cascadeOnUpdate();

            $table->dropForeign(['id_responsavel']);
            $table->foreign('id_responsavel')->references('id')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::table('etapas_documento', function (Blueprint $table): void {
            $table->dropForeign(['id_documento']);
            $table->foreign('id_documento')->references('id')->on('documentos')
                ->cascadeOnDelete()->cascadeOnUpdate();

            $table->dropForeign(['id_utilizador']);
            $table->foreign('id_utilizador')->references('id')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::table('tipos_documento', function (Blueprint $table): void {
            $table->dropForeign(['id_categoria']);
            $table->foreign('id_categoria')->references('id')->on('categorias_documento')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropForeign(['id_fornecedor']);
            $table->foreign('id_fornecedor')->references('id')->on('entidades')->restrictOnDelete();

            $table->dropForeign(['id_cliente']);
            $table->foreign('id_cliente')->references('id')->on('entidades')->restrictOnDelete();

            $table->dropForeign(['id_categoria']);
            $table->foreign('id_categoria')->references('id')->on('categorias_documento')->restrictOnDelete();

            $table->dropForeign(['id_responsavel']);
            $table->foreign('id_responsavel')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('etapas_documento', function (Blueprint $table): void {
            $table->dropForeign(['id_documento']);
            $table->foreign('id_documento')->references('id')->on('documentos')->cascadeOnDelete();

            $table->dropForeign(['id_utilizador']);
            $table->foreign('id_utilizador')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('tipos_documento', function (Blueprint $table): void {
            $table->dropForeign(['id_categoria']);
            $table->foreign('id_categoria')->references('id')->on('categorias_documento')->restrictOnDelete();
        });
    }
};
