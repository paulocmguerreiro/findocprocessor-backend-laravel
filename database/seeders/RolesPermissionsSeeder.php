<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

final class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('app.admin_email', 'admin@findocprocessor.test');
        $password = config('app.admin_initial_password');

        // Em produção nunca criar a credencial fraca conhecida: exige ADMIN_INITIAL_PASSWORD.
        if (app()->isProduction() && ($password === null || $password === '')) {
            Log::warning('seed.admin.ignorado', [
                'motivo' => 'ADMIN_INITIAL_PASSWORD não definida em produção',
            ]);

            return;
        }

        $adminUser = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Admin FinDocProcessor', 'password' => Hash::make((string) ($password ?? 'password'))]
        );
        $adminUser->assignRole('admin');

        // Token de conveniência apenas fora de produção.
        if (! app()->isProduction()) {
            $adminUser->createToken('dev-token', ['api']);
        }
    }
}
