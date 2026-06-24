<?php

declare(strict_types=1);

namespace App\Features\Auth\Login;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class LoginAction
{
    /**
     * @throws ValidationException
     * @throws \Throwable
     */
    public function handle(LoginDto $dados): string
    {
        Log::info('auth.login.tentativa', ['email' => $dados->email, 'ip' => $dados->ip]);

        try {
            $token = DB::transaction(function () use ($dados): string {
                $utilizador = User::where('email', $dados->email)->first();

                if (! $utilizador || ! Hash::check($dados->password, $utilizador->password)) {
                    throw ValidationException::withMessages([
                        'email' => ['As credenciais fornecidas estão incorrectas.'],
                    ]);
                }

                return $utilizador->createToken('api', ['api'])->plainTextToken;
            });

            Log::info('auth.login.sucesso', ['email' => $dados->email]);

            return $token;
        } catch (ValidationException $e) {
            Log::warning('auth.login.falhou', ['email' => $dados->email, 'ip' => $dados->ip]);
            throw $e;
        }
    }
}
