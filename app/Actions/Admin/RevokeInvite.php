<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Invite;

/**
 * Revogar não apaga: o convite continua no histórico (quem usou, quantas
 * vezes), só deixa de aceitar resgate novo. Revogar de novo é no-op.
 */
class RevokeInvite
{
    public function handle(Invite $invite): Invite
    {
        if (! $invite->isRevoked()) {
            $invite->forceFill(['revoked_at' => now()])->save();
        }

        return $invite;
    }
}
