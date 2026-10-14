<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Exceptions\AdminChangeNotAllowedException;
use App\Models\User;

/** Quantos convites um jogador comum pode gerar. A faixa é checada no FormRequest. */
class SetInviteQuota
{
    public function handle(User $target, int $quota): User
    {
        if ($target->is_admin) {
            throw AdminChangeNotAllowedException::quotaForAdmin();
        }

        $target->forceFill(['invite_quota' => $quota])->save();

        return $target;
    }
}
