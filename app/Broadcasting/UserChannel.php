<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

/** Canal privado de cada jogador (`users.{id}`): avisos de troca e amizade. */
class UserChannel
{
    public function join(User $user, int $id): bool
    {
        return $user->id === $id;
    }
}
