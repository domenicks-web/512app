<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\InviteStatus;
use DomainException;

class InviteNotRevocableException extends DomainException
{
    public static function status(InviteStatus $status): self
    {
        return new self(match ($status) {
            InviteStatus::Revoked => 'Esse convite já foi revogado.',
            InviteStatus::Exhausted => 'Esse convite já foi usado até o fim.',
            InviteStatus::Expired => 'Esse convite já expirou.',
            InviteStatus::Active => 'Esse convite não pode ser revogado.',
        });
    }
}
