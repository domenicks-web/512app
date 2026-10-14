<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;

/**
 * Transforma o pedido pendente em amizade. Quem pode aceitar é decidido
 * na `FriendshipPolicy` (só o addressee); aqui só muda o estado.
 */
class AcceptFriendRequest
{
    public function handle(Friendship $request): Friendship
    {
        $request->update([
            'status' => FriendshipStatus::Accepted,
            'accepted_at' => now(),
        ]);

        return $request;
    }
}
