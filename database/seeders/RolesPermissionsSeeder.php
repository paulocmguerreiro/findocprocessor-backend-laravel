<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@findocprocessor.test'],
            ['name' => 'Admin FinDocProcessor', 'password' => Hash::make('password')]
        );
        $adminUser->assignRole('admin');
        $adminUser->createToken('dev-token', ['api']);
    }
}
