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
            'documentos.ver',
            'documentos.criar',
            'documentos.actualizar',
            'documentos.eliminar',
        ];

        foreach ($novasPermissions as $nome) {
            Permission::create(['name' => $nome]);
        }

        Role::findByName('admin')->givePermissionTo($novasPermissions);
        Role::findByName('utilizador')->givePermissionTo('documentos.ver');
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', [
            'documentos.ver',
            'documentos.criar',
            'documentos.actualizar',
            'documentos.eliminar',
        ])->delete();
    }
};
