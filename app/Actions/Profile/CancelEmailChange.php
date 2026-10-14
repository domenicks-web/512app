<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;

class CancelEmailChange
{
    public function handle(User $user): void
    {
        $user->forceFill(['pending_email' => null])->save();
    }
}
