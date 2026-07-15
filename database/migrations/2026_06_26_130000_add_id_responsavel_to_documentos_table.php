<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->foreignId('id_responsavel')->nullable()->after('estado')
                ->constrained('users')->nullOnDelete()
                ->comment('FK para o utilizador responsável (autor do registo/upload); null = utilizador removido');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_responsavel');
        });
    }
};
