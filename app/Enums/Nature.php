<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * As 25 naturezas. O valor (inglês) é o que vai pro banco e pra seed;
 * `label()` é o nome que o jogador vê.
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
        return match ($this) {
            self::Hardy => 'Resistente',
            self::Lonely => 'Solitária',
            self::Brave => 'Corajosa',
            self::Adamant => 'Obstinada',
            self::Naughty => 'Travessa',
            self::Bold => 'Ousada',
            self::Docile => 'Dócil',
            self::Relaxed => 'Relaxada',
            self::Impish => 'Arteira',
            self::Lax => 'Desleixada',
            self::Timid => 'Tímida',
            self::Hasty => 'Apressada',
            self::Serious => 'Séria',
            self::Jolly => 'Alegre',
            self::Naive => 'Ingênua',
            self::Modest => 'Modesta',
            self::Mild => 'Branda',
            self::Quiet => 'Quieta',
            self::Bashful => 'Acanhada',
            self::Rash => 'Imprudente',
            self::Calm => 'Calma',
            self::Gentle => 'Gentil',
            self::Sassy => 'Atrevida',
            self::Careful => 'Cuidadosa',
            self::Quirky => 'Excêntrica',
        };
    }
}
