<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\SuspendPlayer;
use App\Exceptions\SanctionNotAllowedException;
use App\Models\User;
use Illuminate\Console\Command;

class SuspendPlayerCommand extends Command
{
    protected $signature = 'admin:suspend {player : Email ou Nick#1234} {days : Um dos prazos de game.sanctions.suspension_days} {reason : Motivo (vai no email)}';

    protected $description = 'Suspende um jogador por alguns dias, com o motivo';

    public function handle(SuspendPlayer $action): int
    {
        $player = User::findByEmailOrNicknameWithTag((string) $this->argument('player'));

        if ($player === null) {
            $this->error('Jogador não encontrado. Use o email ou Nick#1234.');

            return self::FAILURE;
        }

        try {
            $sanction = $action->handle(null, $player, (int) $this->argument('days'), (string) $this->argument('reason'));
        } catch (SanctionNotAllowedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($player->nicknameWithTag() ?? $player->email).' suspenso até '.$sanction->ends_at?->timezone((string) config('game.display_timezone'))->format('d/m H:i').'.');

        return self::SUCCESS;
    }
}
