<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Troca a senha pelo link do email. Derruba todas as sessões e o "lembrar
 * de mim" (quem esqueceu a senha pode estar com a conta aberta em outro
 * lugar) e avisa o email da conta. O token só vale uma vez.
 */
class ResetForgottenPassword
{
    /**
     * @return bool se o link ainda valia e a senha foi trocada
     */
    public function handle(string $email, string $token, string $newPassword): bool
    {
        $status = Password::reset(
            ['email' => $email, 'token' => $token, 'password' => $newPassword],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();

                Mail::to($user->email)->send(new PasswordChangedMail($user, now(), byReset: true));
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
