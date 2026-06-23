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
            'roles.ver',
            'roles.criar',
            'roles.actualizar',
            'roles.eliminar',
            'utilizadores.atribuir-role',
        ];

        foreach ($novasPermissions as $nome) {
            Permission::create(['name' => $nome]);
        }

        Role::findByName('admin')->givePermissionTo($novasPermissions);
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', [
            'roles.ver',
            'roles.criar',
            'roles.actualizar',
            'roles.eliminar',
            'utilizadores.atribuir-role',
        ])->delete();
    }
};
