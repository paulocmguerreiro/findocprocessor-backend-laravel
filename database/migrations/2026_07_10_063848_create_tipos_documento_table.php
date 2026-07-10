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
        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Identificador único UUID v7');
            $table->string('nome', 255)->unique()->comment('Nome legível do tipo de documento');
            $table->text('descricao')->comment('Descrição livre do tipo de documento, para servir de guia pela IA para categorizar corretamente o documento');
            $table->foreignUuid('id_categoria')->constrained('categorias_documento')->restrictOnDelete()->comment('FK para a categoria de documento');
            $table->string('posicao_empresa_mae', 50)->comment('Posição da empresa-mãe neste tipo de documento: fornecedor ou cliente');
            $table->boolean('espera_data_documento')->default(true)->comment('Indica se se espera extrair a data do documento');
            $table->boolean('espera_fornecedor')->default(true)->comment('Indica se se espera extrair o fornecedor');
            $table->boolean('espera_cliente')->default(true)->comment('Indica se se espera extrair o cliente');
            $table->boolean('espera_valor')->default(true)->comment('Indica se se espera extrair o valor');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_documento');
    }
};
