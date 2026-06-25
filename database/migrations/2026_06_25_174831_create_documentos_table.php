<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Identificador unico UUID v7');
            $table->string('status', 50)->default('PENDENTE')->index()->comment('Estado de processamento do documento');

            $table->foreignUuid('id_fornecedor')->nullable()->constrained('entidades')->nullOnDelete()->comment('FK para a entidade fornecedora');
            $table->foreignUuid('id_cliente')->nullable()->constrained('entidades')->nullOnDelete()->comment('FK para a entidade cliente');
            $table->foreignUuid('id_categoria')->nullable()->constrained('categorias_documento')->nullOnDelete()->comment('FK para a categoria do documento');

            $table->decimal('valor', total: 15, places: 2)->nullable()->comment('Valor monetario do documento; >= 0');
            $table->date('data_documento')->nullable()->index()->comment('Data do documento');

            $table->string('nome_ficheiro_original', 500)->comment('Nome original do ficheiro no upload');
            $table->string('disco_storage', 50)->comment('Nome do disco Laravel onde reside o ficheiro');
            $table->string('nome_ficheiro_storage', 500)->comment('Nome do ficheiro no disco');
            $table->string('hash_sha256', 64)->unique()->comment('SHA-256 do conteudo do ficheiro; previne duplicados');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
