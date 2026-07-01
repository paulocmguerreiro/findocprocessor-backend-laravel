<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create(['name' => 'utilizadores.anonimizar']);

        Role::findByName('admin')->givePermissionTo('utilizadores.anonimizar');
        // role 'utilizador' não recebe esta permissão
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findByName('utilizadores.anonimizar')->delete();
    }
};
