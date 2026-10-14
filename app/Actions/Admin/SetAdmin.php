<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Exceptions\AdminChangeNotAllowedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Promove ou rebaixa. `is_admin` não é mass-assignable de propósito: só
 * muda aqui, via forceFill. Duas travas pra ninguém se trancar pra fora:
 * não dá pra mexer no próprio acesso, e o jogo nunca fica sem admin. Os
 * admins são travados com lockForUpdate pra dois rebaixamentos
 * simultâneos não zerarem a contagem. `$actor` null = linha de comando.
 */
class SetAdmin
{
    public function handle(?User $actor, User $target, bool $isAdmin): User
    {
        if ($actor !== null && $actor->is($target)) {
            throw AdminChangeNotAllowedException::selfDemotion();
        }

        return DB::transaction(function () use ($target, $isAdmin): User {
            $adminIds = User::where('is_admin', true)->lockForUpdate()->pluck('id');

            if (! $isAdmin && $adminIds->contains($target->id) && $adminIds->count() <= 1) {
                throw AdminChangeNotAllowedException::lastAdmin();
            }

            $target->forceFill(['is_admin' => $isAdmin])->save();

            return $target;
        });
    }
}
