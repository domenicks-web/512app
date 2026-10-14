<?php

declare(strict_types=1);

namespace App\Actions\Admin;

/**
 * Sorteia o sufixo do código ("7X2K") no alfabeto sem caracteres ambíguos.
 * Só o servidor sorteia: a prévia do painel vem daqui.
 */
class GenerateInviteSuffix
{
    public function handle(): string
    {
        $alphabet = (string) config('game.invites.code_alphabet');
        $length = (int) config('game.invites.code_suffix_length');
        $suffix = '';

        for ($i = 0; $i < $length; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $suffix;
    }
}
