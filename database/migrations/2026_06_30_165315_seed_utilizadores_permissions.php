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

        $novasPermissions = [
            'utilizadores.ver',
            'utilizadores.criar',
            'utilizadores.actualizar',
            'utilizadores.eliminar',
        ];

        foreach ($novasPermissions as $nome) {
            Permission::create(['name' => $nome]);
        }

        Role::findByName('admin')->givePermissionTo($novasPermissions);
        // role 'utilizador' não recebe nenhuma destas permissões
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', [
            'utilizadores.ver',
            'utilizadores.criar',
            'utilizadores.actualizar',
            'utilizadores.eliminar',
        ])->delete();
    }
};
