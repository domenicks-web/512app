<?php

declare(strict_types=1);

namespace App\Enums;

enum InviteStatus: string
{
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
