<?php

declare(strict_types=1);

namespace App\Enums;

enum InviteStatus: string
{
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'ativo',
            self::Exhausted => 'esgotado',
            self::Expired => 'expirado',
            self::Revoked => 'revogado',
        };
    }

    public static function fromLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->label() === mb_strtolower($label)) {
                return $case;
            }
        }

        return null;
    }
}
