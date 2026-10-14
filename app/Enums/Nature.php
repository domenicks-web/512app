<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * As 25 naturezas, na ordem dos jogos (a seed depende dela). O jogador
 * vê o nome dos jogos, em inglês, e o efeito que ela teria lá.
 */
enum Nature: string
{
    case Hardy = 'hardy';
    case Lonely = 'lonely';
    case Brave = 'brave';
    case Adamant = 'adamant';
    case Naughty = 'naughty';
    case Bold = 'bold';
    case Docile = 'docile';
    case Relaxed = 'relaxed';
    case Impish = 'impish';
    case Lax = 'lax';
    case Timid = 'timid';
    case Hasty = 'hasty';
    case Serious = 'serious';
    case Jolly = 'jolly';
    case Naive = 'naive';
    case Modest = 'modest';
    case Mild = 'mild';
    case Quiet = 'quiet';
    case Bashful = 'bashful';
    case Rash = 'rash';
    case Calm = 'calm';
    case Gentle = 'gentle';
    case Sassy = 'sassy';
    case Careful = 'careful';
    case Quirky = 'quirky';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * O que ela faz nos jogos (+10% num atributo, −10% noutro), só como
     * curiosidade: aqui a natureza não mexe em nada. Na ordem dos jogos,
     * a posição na lista diz o atributo que sobe (÷5) e o que cai (resto);
     * as cinco da diagonal são neutras.
     */
    public function effect(): string
    {
        $stats = ['Atq', 'Def', 'Vel', 'AtqE', 'DefE'];
        $index = (int) array_search($this, self::cases(), true);
        $up = intdiv($index, 5);
        $down = $index % 5;

        return $up === $down ? 'Neutra' : "+{$stats[$up]} −{$stats[$down]}";
    }
}
