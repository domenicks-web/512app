<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\RevokeInvite;
use App\Exceptions\InviteNotRevocableException;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Console\Command;

class RevokeInviteCommand extends Command
{
    protected $signature = 'invite:revoke {code : Código do convite, ex: RAMON-7X2K}';

    protected $description = 'Revoga um convite ativo (quem já entrou com ele continua)';

    public function handle(RevokeInvite $action): int
    {
        $invite = Invite::where('code', mb_strtoupper((string) $this->argument('code')))->first();

        if ($invite === null) {
            $this->error('Convite não encontrado.');

            return self::FAILURE;
        }

        $admin = User::where('is_admin', true)->orderBy('id')->first();

        if ($admin === null) {
            $this->error('Nenhum admin encontrado. Rode admin:promote primeiro.');

            return self::FAILURE;
        }

        try {
            $action->handle($admin, $invite);
        } catch (InviteNotRevocableException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$invite->code} revogado.");

        return self::SUCCESS;
    }
}
