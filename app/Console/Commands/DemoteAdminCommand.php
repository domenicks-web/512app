<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\SetAdmin;
use App\Exceptions\AdminChangeNotAllowedException;
use App\Models\User;
use Illuminate\Console\Command;

class DemoteAdminCommand extends Command
{
    protected $signature = 'admin:demote {player : Email ou Nick#1234}';

    protected $description = 'Tira o admin de um jogador (recusa se for o último admin)';

    public function handle(SetAdmin $action): int
    {
        $player = User::findByEmailOrNicknameWithTag((string) $this->argument('player'));

        if ($player === null) {
            $this->error('Jogador não encontrado. Use o email ou Nick#1234.');

            return self::FAILURE;
        }

        try {
            $action->handle(null, $player, false);
        } catch (AdminChangeNotAllowedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($player->nicknameWithTag() ?? $player->email).' voltou a ser jogador.');

        return self::SUCCESS;
    }
}
