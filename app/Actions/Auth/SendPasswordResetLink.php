<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

/**
 * Manda o link do "esqueci a senha". Não conta pra quem pediu se o email
 * tem conta nem se caiu no limite de envio: a resposta é sempre a mesma.
 */
class SendPasswordResetLink
{
    public function handle(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }
}
