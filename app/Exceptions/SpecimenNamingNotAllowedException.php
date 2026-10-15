<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/** Quem pode dar apelido ao espécime (mensagem pro jogador). */
class SpecimenNamingNotAllowedException extends DomainException
{
    public static function notOwner(): self
    {
        return new self('Esse espécime não é seu.');
    }

    public static function alreadyNamed(): self
    {
        return new self('Esse espécime já tem apelido.');
    }
}
