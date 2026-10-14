<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\SetAdmin;
use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdminCommand extends Command
{
    protected $signature = 'admin:promote {player : Email ou Nick#1234}';

    protected $description = 'Torna um jogador admin (é assim que nasce o primeiro admin, o fundador)';

    public function handle(SetAdmin $action): int
    {
        $player = User::findByEmailOrNicknameWithTag((string) $this->argument('player'));

        if ($player === null) {
            $this->error('Jogador não encontrado. Use o email ou Nick#1234.');

            return self::FAILURE;
        }

        $action->handle(null, $player, true);
        $this->info(($player->nicknameWithTag() ?? $player->email).' agora é admin.');

        return self::SUCCESS;
    }
}
