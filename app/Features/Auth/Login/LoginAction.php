<?php

declare(strict_types=1);

namespace App\Features\Auth\Login;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginAction
{
    /**
     * @throws ValidationException
     * @throws \Throwable
     */
    public function handle(string $email, string $password): string
    {
        return DB::transaction(function () use ($email, $password): string {
            $utilizador = User::where('email', $email)->first();

            if (! $utilizador || ! Hash::check($password, $utilizador->password)) {
                throw ValidationException::withMessages([
                    'email' => ['As credenciais fornecidas estão incorrectas.'],
                ]);
            }

            return $utilizador->createToken('api', ['api'])->plainTextToken;
        });
    }
}
