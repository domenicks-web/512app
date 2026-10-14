<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;

/** Manda de novo o link de confirmação. Devolve false se o email já foi confirmado. */
class ResendVerificationEmail
{
    public function handle(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();

        return true;
    }
}
