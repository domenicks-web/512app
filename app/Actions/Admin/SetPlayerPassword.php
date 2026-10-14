<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Exceptions\AdminChangeNotAllowedException;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Admin troca a senha de outro jogador (ex: esqueceu e não tem acesso ao
 * email). Derruba todas as sessões dele e o "lembrar de mim", e avisa o
 * email da conta. A própria senha se troca no editar perfil.
 */
class SetPlayerPassword
{
    public function handle(User $actor, User $target, string $newPassword): void
    {
        if ($actor->is($target)) {
            throw AdminChangeNotAllowedException::ownPassword();
        }

        $target->forceFill([
            'password' => $newPassword,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('sessions')->where('user_id', $target->id)->delete();

        Mail::to($target->email)->send(new PasswordChangedMail($target, now(), byAdmin: true));
    }
}
