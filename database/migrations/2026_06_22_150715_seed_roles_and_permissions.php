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

        $todasPermissions = [
            'entidades.ver',
            'entidades.criar',
            'entidades.actualizar',
            'entidades.eliminar',
            'categorias-documento.ver',
            'categorias-documento.criar',
            'categorias-documento.actualizar',
            'categorias-documento.eliminar',
        ];

        foreach ($todasPermissions as $nome) {
            Permission::create(['name' => $nome]);
        }

        Role::create(['name' => 'admin'])->syncPermissions($todasPermissions);
        Role::create(['name' => 'utilizador'])->syncPermissions([
            'entidades.ver',
            'categorias-documento.ver',
        ]);
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::whereIn('name', ['admin', 'utilizador'])->delete();
        Permission::whereIn('name', [
            'entidades.ver', 'entidades.criar', 'entidades.actualizar', 'entidades.eliminar',
            'categorias-documento.ver', 'categorias-documento.criar',
            'categorias-documento.actualizar', 'categorias-documento.eliminar',
        ])->delete();
    }
};
