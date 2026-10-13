<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;

/**
 * `is_admin` não é mass-assignable de propósito: só passa por aqui e pelo
 * RevokeAdmin, sempre via forceFill.
 */
class GrantAdmin
{
    public function handle(User $target): User
    {
        $target->forceFill(['is_admin' => true])->save();

        return $target;
    }
}
