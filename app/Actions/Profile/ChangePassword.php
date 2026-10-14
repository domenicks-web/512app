<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Grava a senha nova e tira o jogador dos outros aparelhos: apaga as
 * outras sessões e troca o remember token (senão o "lembrar de mim" de
 * outro aparelho logaria de novo). Avisa o email da conta.
 */
class ChangePassword
{
    public function handle(User $user, string $newPassword, string $currentSessionId): void
    {
        $user->forceFill([
            'password' => $newPassword,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        Mail::to($user->email)->send(new PasswordChangedMail($user, now()));
    }
}
