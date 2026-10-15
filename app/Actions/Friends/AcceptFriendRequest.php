<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Actions\Notify\BuildNotice;
use App\Actions\Notify\Notifier;
use App\Enums\FriendshipStatus;
use App\Models\Friendship;

/**
 * Transforma o pedido pendente em amizade. Quem pode aceitar é decidido
 * na `FriendshipPolicy` (só o addressee); aqui só muda o estado. A amizade
 * automática com quem convidou (`BefriendInviter`) chama com `$notify =
 * false`: os dois já estão entrando no jogo, aviso seria ruído.
 */
class AcceptFriendRequest
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly BuildNotice $notices,
    ) {}

    public function handle(Friendship $request, bool $notify = true): Friendship
    {
        $request->update([
            'status' => FriendshipStatus::Accepted,
            'accepted_at' => now(),
        ]);

        if ($notify) {
            $this->notifier->toPlayer($request->requester_id, $this->notices->friendAccepted($request));
        }

        return $request;
    }
}
