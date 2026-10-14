<?php

declare(strict_types=1);

namespace App\Exceptions;

use Carbon\CarbonInterface;
use DomainException;

class PackOnCooldownException extends DomainException
{
    public function __construct(public readonly CarbonInterface $availableAt)
    {
        parent::__construct('O próximo pack ainda não chegou.');
    }
}
