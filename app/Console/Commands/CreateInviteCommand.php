<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\CreateInvite;
use App\Actions\Admin\DefaultInvitePrefix;
use App\Actions\Data\CreateInviteData;
use App\Models\User;
use Illuminate\Console\Command;

class CreateInviteCommand extends Command
{
    protected $signature = 'invite:create
        {--prefix= : Prefixo do código, só letras e números (padrão: nickname de quem cria)}
        {--uses=1 : Quantas contas o convite pode criar}
        {--days=30 : Dias até expirar}
        {--never : Convite sem validade (ignora --days)}
        {--by= : Email ou Nick#1234 do admin que assina (padrão: o primeiro admin)}';

    protected $description = 'Gera um código de convite sem precisar do painel';

    public function handle(CreateInvite $action, DefaultInvitePrefix $defaultPrefix): int
    {
        $creator = $this->option('by') !== null
            ? User::findByEmailOrNicknameWithTag((string) $this->option('by'))
            : User::where('is_admin', true)->orderBy('id')->first();

        if ($creator === null || ! $creator->is_admin) {
            $this->error('Admin não encontrado. Rode admin:promote primeiro ou passe --by de um admin.');

            return self::FAILURE;
        }

        $maxLength = (int) config('game.invites.code_prefix_max_length');
        $prefix = $this->option('prefix') !== null
            ? mb_strtoupper((string) $this->option('prefix'))
            : $defaultPrefix->handle($creator);

        if (preg_match("/^[A-Z0-9]{1,{$maxLength}}$/", $prefix) !== 1) {
            $this->error("--prefix aceita só letras e números, até {$maxLength} caracteres.");

            return self::FAILURE;
        }

        $uses = filter_var($this->option('uses'), FILTER_VALIDATE_INT, ['options' => [
            'min_range' => config('game.invites.max_uses.min'),
            'max_range' => config('game.invites.max_uses.max'),
        ]]);

        if ($uses === false) {
            $this->error('--uses vai de '.config('game.invites.max_uses.min').' a '.config('game.invites.max_uses.max').'.');

            return self::FAILURE;
        }

        $days = $this->option('never') ? null : filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($days === false) {
            $this->error('--days precisa ser um inteiro maior que zero (ou use --never).');

            return self::FAILURE;
        }

        $invite = $action->handle($creator, new CreateInviteData(
            prefix: $prefix,
            suffix: null,
            maxUses: $uses,
            expiresAt: $days !== null ? now()->addDays($days) : null,
        ));

        $this->line($invite->code);

        return self::SUCCESS;
    }
}
