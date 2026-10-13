<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class AdminChangeNotAllowedException extends DomainException
{
    public static function selfDemotion(): self
    {
        return new self('Você não pode remover o seu próprio acesso de admin.');
    }

    public static function lastAdmin(): self
    {
        return new self('Não dá pra remover o último admin do jogo.');
    }
}
