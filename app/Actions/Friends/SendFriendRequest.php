<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Actions\Notify\BuildNotice;
use App\Actions\Notify\Notifier;
use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pedido de amizade pelo `nickname#tag` completo (o nickname sem
 * diferenciar maiúscula). Se a outra pessoa já tinha mandado pedido, aceita
 * o dela em vez de criar outro. As duas linhas de `users` ficam travadas
 * na transação pra dois pedidos cruzados simultâneos não virarem duas
 * linhas.
 */
class SendFriendRequest
{
    public function __construct(
        private readonly AcceptFriendRequest $accept,
        private readonly Notifier $notifier,
        private readonly BuildNotice $notices,
    ) {}

    public function handle(User $sender, string $handle): Friendship
    {
        $target = $this->findTarget($handle);

        if ($target->id === $sender->id) {
            $this->fail('Esse é você');
        }

        return DB::transaction(function () use ($sender, $target): Friendship {
            User::query()->whereKey([$sender->id, $target->id])->orderBy('id')->lockForUpdate()->get();

            $existing = Friendship::query()->between($sender, $target)->first();

            if ($existing?->status === FriendshipStatus::Accepted) {
                $this->fail('Vocês já são amigos');
            }

            if ($existing !== null && $existing->requester_id === $sender->id) {
                $this->fail('Você já mandou um pedido pra essa pessoa');
            }

            if ($existing !== null) {
                return $this->accept->handle($existing);
            }

            $friendship = Friendship::create([
                'requester_id' => $sender->id,
                'addressee_id' => $target->id,
                'status' => FriendshipStatus::Pending,
            ]);

            $this->notifier->toPlayer($target->id, $this->notices->friendRequest($friendship));

            return $friendship;
        });
    }

    private function findTarget(string $handle): User
    {
        $handle = trim($handle);
        $separator = strrpos($handle, '#');
        $nickname = $separator === false ? '' : substr($handle, 0, $separator);
        $tag = $separator === false ? '' : substr($handle, $separator + 1);

        if ($nickname === '' || $tag === '') {
            $this->fail('Use o formato nickname#1234');
        }

        return User::query()
            ->active()
            ->whereRaw('lower(nickname) = ?', [mb_strtolower($nickname)])
            ->where('tag', $tag)
            ->first()
            ?? $this->fail('Não achei ninguém com esse nickname#tag');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['handle' => $message]);
    }
}
