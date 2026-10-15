<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\BanPlayer;
use App\Exceptions\SanctionNotAllowedException;
use App\Models\User;
use Illuminate\Console\Command;

class BanPlayerCommand extends Command
{
    protected $signature = 'admin:ban {player : Email ou Nick#1234} {reason : Motivo (vai no email)} {--force : Não pede confirmação}';

    protected $description = 'Bane um jogador para sempre e liberta os espécimes dele';

    public function handle(BanPlayer $action): int
    {
        $player = User::findByEmailOrNicknameWithTag((string) $this->argument('player'));

        if ($player === null) {
            $this->error('Jogador não encontrado. Use o email ou Nick#1234.');

            return self::FAILURE;
        }

        $name = $player->nicknameWithTag() ?? $player->email;

        if (! $this->option('force') && ! $this->confirm("Banir {$name} para sempre e libertar os espécimes dele?")) {
            $this->line('Nada feito.');

            return self::SUCCESS;
        }

        try {
            $action->handle(null, $player, (string) $this->argument('reason'));
        } catch (SanctionNotAllowedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$name} banido.");

        return self::SUCCESS;
    }
}
