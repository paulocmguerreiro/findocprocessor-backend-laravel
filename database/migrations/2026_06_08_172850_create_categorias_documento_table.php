<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categorias_documento', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Identificador único UUID v7');
            $table->string('nome', 255)->index()->comment('Nome legível da categoria de documento');
            $table->string('slug', 255)->unique()->comment('Identificador textual único, gerado a partir do nome');
            $table->string('tipo_movimento', 50)->comment('Tipo de movimento financeiro: debito, credito ou neutro');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias_documento');
    }
};
