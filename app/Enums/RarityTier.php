<?php

declare(strict_types=1);

namespace App\Enums;

enum RarityTier: string
{
    case Common = 'comum';
    case Uncommon = 'incomum';
    case Rare = 'raro';
    case Legendary = 'lendario';

    public function label(): string
    {
        return match ($this) {
            self::Common => 'Comum',
            self::Uncommon => 'Incomum',
            self::Rare => 'Raro',
            self::Legendary => 'Lendário',
        };
    }
}
