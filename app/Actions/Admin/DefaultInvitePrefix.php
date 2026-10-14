<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Str;

/** Prefixo sugerido no painel: o nickname de quem cria, só `[A-Z0-9]`. */
class DefaultInvitePrefix
{
    public function handle(User $creator): string
    {
        $prefix = Str::of((string) $creator->nickname)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->limit((int) config('game.invites.code_prefix_max_length'), '')
            ->toString();

        return $prefix !== '' ? $prefix : (string) config('game.invites.code_fallback_prefix');
    }
}
