<?php

declare(strict_types=1);

namespace App\Enums;

enum RarityTier: string
{
    case Common = 'comum';
    case Uncommon = 'incomum';
    case Rare = 'raro';
    case Legendary = 'lendario';
}
