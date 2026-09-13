<?php

declare(strict_types=1);

namespace App\Enums;

enum PokemonType: string
{
    case Normal = 'normal';
    case Fire = 'fire';
    case Water = 'water';
    case Electric = 'electric';
    case Grass = 'grass';
    case Ice = 'ice';
    case Fighting = 'fighting';
    case Poison = 'poison';
    case Ground = 'ground';
    case Flying = 'flying';
    case Psychic = 'psychic';
    case Bug = 'bug';
    case Rock = 'rock';
    case Ghost = 'ghost';
    case Dragon = 'dragon';
}
