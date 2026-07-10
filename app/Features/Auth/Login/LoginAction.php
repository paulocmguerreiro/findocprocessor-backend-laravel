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
        $emailMascarado = $this->mascararEmail($dados->email);

        Log::info('auth.login.tentativa', ['email' => $emailMascarado, 'ip' => $dados->ip]);

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

            Log::info('auth.login.sucesso', ['email' => $emailMascarado]);

            return $token;
        } catch (ValidationException $e) {
            Log::warning('auth.login.falhou', ['email' => $emailMascarado, 'ip' => $dados->ip]);
            throw $e;
        }
    }

    /**
     * Pseudonimiza o email para os logs (RGPD — dado pessoal nunca em claro):
     *
     * mantém a primeira letra da parte local e o domínio (`u***@exemplo.pt`).
     */
    private function mascararEmail(string $email): string
    {
        $partes = explode('@', $email, 2);

        if (count($partes) < 2) {
            return '***';
        }

        return mb_substr($partes[0], 0, 1).'***@'.$partes[1];
    }
}
