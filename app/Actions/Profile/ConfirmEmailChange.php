<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Mail\EmailChangedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Abre o link do email novo. Só troca se o email do link ainda for o
 * pendente e ninguém tiver cadastrado ele no meio-tempo. Avisa o email
 * antigo. Devolve a mensagem que a tela mostra.
 */
class ConfirmEmailChange
{
    public function handle(User $user, string $email): string
    {
        if ($user->pending_email !== $email) {
            return 'Esse link não vale mais. Peça a troca de novo.';
        }

        if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
            $user->forceFill(['pending_email' => null])->save();

            return 'Esse email passou a ser usado por outra conta. Escolha outro.';
        }

        $oldEmail = $user->email;
        $user->forceFill([
            'email' => $email,
            'email_verified_at' => now(),
            'pending_email' => null,
        ])->save();

        Mail::to($oldEmail)->send(new EmailChangedMail($user, $email));

        return 'Email trocado.';
    }
}
