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
        // SQLite não suporta dropForeign por nome; a constraint é apenas relevante em MySQL/prod
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('documentos', function (Blueprint $t): void {
            $t->dropForeign('documentos_id_fornecedor_foreign');
            $t->dropForeign('documentos_id_cliente_foreign');

            $t->foreignUuid('id_fornecedor')->nullable()->change()->constrained('entidades')->restrictOnDelete();
            $t->foreignUuid('id_cliente')->nullable()->change()->constrained('entidades')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('documentos', function (Blueprint $t): void {
            $t->dropForeign('documentos_id_fornecedor_foreign');
            $t->dropForeign('documentos_id_cliente_foreign');

            $t->foreignUuid('id_fornecedor')->nullable()->change()->constrained('entidades')->nullOnDelete();
            $t->foreignUuid('id_cliente')->nullable()->change()->constrained('entidades')->nullOnDelete();
        });
    }
};
