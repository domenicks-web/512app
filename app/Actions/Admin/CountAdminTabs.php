<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Invite;
use App\Models\User;

/** Contadores das abas do painel: convites ativos e total de jogadores. */
class CountAdminTabs
{
    /**
     * @return array{invites: int, players: int}
     */
    public function handle(): array
    {
        return [
            'invites' => Invite::active()->count(),
            'players' => User::count(),
        ];
    }
}
