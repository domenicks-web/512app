<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;

/** Zera o cooldown: o próximo pack do jogador fica disponível agora. */
class GrantNextPack
{
    public function handle(User $user): User
    {
        $user->forceFill(['next_pack_at' => null])->save();

        return $user;
    }
}
