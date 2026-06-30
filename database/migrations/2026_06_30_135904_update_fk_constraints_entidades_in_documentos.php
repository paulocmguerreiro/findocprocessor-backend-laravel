<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $t): void {
            $t->dropForeign('documentos_id_fornecedor_foreign');
            $t->dropForeign('documentos_id_cliente_foreign');

            $t->foreignUuid('id_fornecedor')->nullable()->change()->constrained('entidades')->restrictOnDelete();
            $t->foreignUuid('id_cliente')->nullable()->change()->constrained('entidades')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $t): void {
            $t->dropForeign('documentos_id_fornecedor_foreign');
            $t->dropForeign('documentos_id_cliente_foreign');

            $t->foreignUuid('id_fornecedor')->nullable()->change()->constrained('entidades')->nullOnDelete();
            $t->foreignUuid('id_cliente')->nullable()->change()->constrained('entidades')->nullOnDelete();
        });
    }
};
