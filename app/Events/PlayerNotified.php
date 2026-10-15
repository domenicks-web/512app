<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Data\Notice;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * O único evento em tempo real: um aviso pra um canal (`users.{id}` ou
 * `group`). Sai na hora (sem fila); quem dispara é o `Notifier`, depois do
 * commit.
 */
class PlayerNotified implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $channel,
        public readonly Notice $notice,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->channel);
    }

    public function broadcastAs(): string
    {
        return 'notice';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->notice->toArray();
    }
}
