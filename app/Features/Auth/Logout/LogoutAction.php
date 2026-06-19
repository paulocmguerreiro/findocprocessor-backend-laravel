<?php

declare(strict_types=1);

namespace App\Features\Auth\Logout;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LogoutAction
{
    /**
     * @throws \Throwable
     */
    public function handle(User $utilizador): void
    {
        DB::transaction(function () use ($utilizador): void {
            $utilizador->currentAccessToken()->delete();
        });
    }
}
