<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\SanctionType;
use App\Exceptions\SanctionNotAllowedException;
use App\Models\User;

/** O admin encerra a suspensão antes do prazo. Banimento não se encerra. */
class LiftSuspension
{
    public function handle(?User $admin, User $target): void
    {
        $sanction = $target->activeSanction();

        if ($sanction?->type !== SanctionType::Suspension) {
            throw SanctionNotAllowedException::notSuspended();
        }

        $sanction->update(['lifted_at' => now(), 'lifted_by' => $admin?->id]);
    }
}
