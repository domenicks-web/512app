<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Exceptions\AdminChangeNotAllowedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Duas travas pra ninguém se trancar pra fora: não dá pra rebaixar a si
 * mesmo, e o jogo nunca fica sem admin. Os admins são travados com
 * lockForUpdate pra dois rebaixamentos simultâneos não zerarem a contagem.
 */
class RevokeAdmin
{
    public function handle(User $actor, User $target): User
    {
        if ($actor->is($target)) {
            throw AdminChangeNotAllowedException::selfDemotion();
        }

        return DB::transaction(function () use ($target): User {
            $adminIds = User::where('is_admin', true)->lockForUpdate()->pluck('id');

            if (! $adminIds->contains($target->id)) {
                return $target;
            }

            if ($adminIds->count() <= 1) {
                throw AdminChangeNotAllowedException::lastAdmin();
            }

            $target->forceFill(['is_admin' => false])->save();

            return $target;
        });
    }
}
