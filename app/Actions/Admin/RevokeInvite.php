<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\InviteStatus;
use App\Exceptions\InviteNotRevocableException;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Revogar não apaga: o convite continua no histórico (quem usou, quantas
 * vezes), só deixa de aceitar resgate novo. Só convite ativo pode ser
 * revogado; o lock segura um cadastro que esteja resgatando ao mesmo tempo.
 */
class RevokeInvite
{
    public function handle(User $actor, Invite $invite): Invite
    {
        return DB::transaction(function () use ($actor, $invite): Invite {
            $locked = Invite::whereKey($invite->id)->lockForUpdate()->firstOrFail();

            if ($locked->status() !== InviteStatus::Active) {
                throw InviteNotRevocableException::status($locked->status());
            }

            $locked->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();

            return $locked;
        });
    }
}
