<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;

class FriendshipPolicy
{
    /** Só quem recebeu aceita, e só enquanto é pedido. */
    public function accept(User $user, Friendship $friendship): bool
    {
        return $friendship->addressee_id === $user->id
            && $friendship->status === FriendshipStatus::Pending;
    }

    /** Recusar, cancelar ou desfazer: qualquer um dos dois lados. */
    public function delete(User $user, Friendship $friendship): bool
    {
        return in_array($user->id, [$friendship->requester_id, $friendship->addressee_id], true);
    }
}
