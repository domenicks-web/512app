<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\LiftSuspension;
use App\Exceptions\SanctionNotAllowedException;
use App\Models\User;
use Illuminate\Console\Command;

class LiftSuspensionCommand extends Command
{
    protected $signature = 'admin:lift {player : Email ou Nick#1234}';

    protected $description = 'Encerra a suspensão de um jogador antes do prazo';

    public function handle(LiftSuspension $action): int
    {
        $player = User::findByEmailOrNicknameWithTag((string) $this->argument('player'));

        if ($player === null) {
            $this->error('Jogador não encontrado. Use o email ou Nick#1234.');

            return self::FAILURE;
        }

        try {
            $action->handle(null, $player);
        } catch (SanctionNotAllowedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Suspensão de '.($player->nicknameWithTag() ?? $player->email).' encerrada.');

        return self::SUCCESS;
    }
}
