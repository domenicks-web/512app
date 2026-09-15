<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

/**
 * Gera a tag de 4 dígitos que, junto do nickname, identifica o jogador
 * (igual ao par nickname#tag da Riot). Único é o par (nickname, tag), nunca
 * o nickname sozinho — por isso a checagem de colisão é sempre escopada a
 * um nickname específico.
 */
class GenerateUserTag
{
    public function __construct(private readonly int $digits = 4) {}

    public function handle(string $nickname): string
    {
        $max = (10 ** $this->digits) - 1;

        do {
            $tag = str_pad((string) random_int(0, $max), $this->digits, '0', STR_PAD_LEFT);
        } while (User::where('nickname', $nickname)->where('tag', $tag)->exists());

        return $tag;
    }
}
