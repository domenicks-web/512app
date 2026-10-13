<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Gera o código no formato do SPEC ("RAMON-7X2K"): prefixo com o nickname de
 * quem criou e um sufixo aleatório num alfabeto sem caracteres ambíguos
 * (sem 0/O, 1/I/L), pra dar pra ditar o código por voz sem erro.
 */
class GenerateInviteCode
{
    public function handle(User $creator): string
    {
        $prefix = $this->prefixFor($creator);
        $alphabet = config('game.invites.code_alphabet');
        $suffixLength = config('game.invites.code_suffix_length');

        do {
            $suffix = '';

            for ($i = 0; $i < $suffixLength; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $code = "{$prefix}-{$suffix}";
        } while (Invite::where('code', $code)->exists());

        return $code;
    }

    private function prefixFor(User $creator): string
    {
        $prefix = Str::of((string) $creator->nickname)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->limit(config('game.invites.code_prefix_max_length'), '')
            ->toString();

        return $prefix !== '' ? $prefix : config('game.invites.code_fallback_prefix');
    }
}
