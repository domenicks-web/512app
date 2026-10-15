<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

/** Canal do grupo (`group`): destaque do feed. Só quem já está jogando. */
class GroupChannel
{
    public function join(User $user): bool
    {
        return $user->hasCompletedProfile();
    }
}
