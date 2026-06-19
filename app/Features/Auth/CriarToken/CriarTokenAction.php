<?php

declare(strict_types=1);

namespace App\Features\Auth\CriarToken;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CriarTokenAction
{
    /**
     * @throws \Throwable
     */
    public function handle(User $utilizador, string $nomeToken): string
    {
        return DB::transaction(
            fn (): string => $utilizador->createToken($nomeToken, ['api'])->plainTextToken
        );
    }
}
