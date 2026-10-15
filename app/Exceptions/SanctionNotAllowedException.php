<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/** Regra de quem pode ser suspenso ou banido (mensagem pro admin). */
class SanctionNotAllowedException extends DomainException
{
    public static function self(): self
    {
        return new self('Você não pode se punir.');
    }

    public static function admin(): self
    {
        return new self('Tire o papel de admin antes.');
    }

    public static function system(): self
    {
        return new self('Essa conta é do sistema.');
    }

    public static function alreadySanctioned(): self
    {
        return new self('Esse jogador já está suspenso ou banido.');
    }

    public static function invalidDays(): self
    {
        return new self('Prazo inválido.');
    }

    public static function notSuspended(): self
    {
        return new self('Esse jogador não está suspenso.');
    }
}
