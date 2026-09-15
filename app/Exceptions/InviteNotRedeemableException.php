<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class InviteNotRedeemableException extends DomainException
{
    public static function code(string $code): self
    {
        return new self("Convite \"{$code}\" não existe, expirou ou já atingiu o limite de usos.");
    }
}
