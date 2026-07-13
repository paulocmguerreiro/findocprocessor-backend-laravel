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
            $table->index(['status', 'updated_at'], 'documentos_status_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropIndex('documentos_status_updated_at_index');
        });
    }
};
