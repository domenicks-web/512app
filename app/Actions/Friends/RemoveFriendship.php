<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Models\Friendship;

/**
 * Recusar, cancelar e desfazer são a mesma coisa no banco: a linha some e
 * os dois podem mandar pedido de novo depois. Quem pode é decidido na
 * `FriendshipPolicy`.
 */
class RemoveFriendship
{
    public function handle(Friendship $friendship): void
    {
        $friendship->delete();
    }
}
