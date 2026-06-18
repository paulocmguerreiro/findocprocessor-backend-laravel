<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entidades', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Identificador unico UUID v7');
            $table->string('nome', 255)->index()->comment('Nome da entidade');
            $table->string('nif', 255)->index()->comment('Numero de Identificacao Fiscal');
            $table->boolean('e_cliente')->default(false)->index()->comment('Verdadeiro se a entidade e cliente');
            $table->boolean('e_fornecedor')->default(false)->index()->comment('Verdadeiro se a entidade e fornecedor');
            $table->boolean('e_empresa_aplicacao')->default(false)->index()->comment('Verdadeiro se e a empresa mae da aplicacao');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'CREATE UNIQUE INDEX unica_empresa_mae_idx ON entidades (e_empresa_aplicacao) WHERE (e_empresa_aplicacao = 1)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('DROP INDEX IF EXISTS unica_empresa_mae_idx ON entidades');
        }

        Schema::dropIfExists('entidades');
    }
};
