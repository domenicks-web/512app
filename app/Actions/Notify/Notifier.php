<?php

declare(strict_types=1);

namespace App\Actions\Notify;

use App\Actions\Data\Notice;
use App\Events\PlayerNotified;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Entrega os avisos: espera a transação gravar e, se o Reverb estiver fora
 * do ar, só registra no log. Aviso nunca derruba a ação do jogo.
 */
class Notifier
{
    public function toPlayer(int $userId, Notice $notice): void
    {
        $this->send("users.{$userId}", $notice);
    }

    public function toGroup(Notice $notice): void
    {
        $this->send('group', $notice);
    }

    private function send(string $channel, Notice $notice): void
    {
        DB::afterCommit(function () use ($channel, $notice): void {
            try {
                event(new PlayerNotified($channel, $notice));
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
