<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;

/**
 * Quem criou o convite vira amigo de quem acabou de entrar. Se já existe
 * linha entre os dois, um pedido pendente é aceito; nada é duplicado.
 */
class BefriendInviter
{
    public function __construct(private readonly AcceptFriendRequest $accept) {}

    public function handle(User $user): void
    {
        $inviter = $user->invitedBy?->createdBy;

        if ($inviter === null || $inviter->id === $user->id) {
            return;
        }

        $existing = Friendship::query()->between($user, $inviter)->first();

        if ($existing?->status === FriendshipStatus::Pending) {
            $this->accept->handle($existing, notify: false);
        }

        if ($existing !== null) {
            return;
        }

        Friendship::create([
            'requester_id' => $inviter->id,
            'addressee_id' => $user->id,
            'status' => FriendshipStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }
}
