<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Mail\ConfirmNewEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Guarda o email novo como pendente e manda pra ele um link assinado. O
 * email só troca quando o link é aberto (`ConfirmEmailChange`). Um pedido
 * novo substitui o anterior: o link antigo deixa de bater com o pendente.
 */
class RequestEmailChange
{
    public function handle(User $user, string $newEmail): void
    {
        $user->forceFill(['pending_email' => $newEmail])->save();

        $minutes = (int) config('game.profile.email_change_link_minutes');
        $url = URL::temporarySignedRoute('profile.email.confirm', now()->addMinutes($minutes), ['email' => $newEmail]);

        Mail::to($newEmail)->send(new ConfirmNewEmailMail($user, $url, $minutes));
    }
}
