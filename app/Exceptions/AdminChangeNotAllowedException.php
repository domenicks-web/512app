<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class AdminChangeNotAllowedException extends DomainException
{
    /** Mexer no próprio acesso é 403; o resto é regra de negócio (422). */
    public function __construct(string $message, public readonly bool $isSelfChange = false)
    {
        parent::__construct($message);
    }

    public static function selfDemotion(): self
    {
        return new self('Você não pode remover o seu próprio acesso de admin.', isSelfChange: true);
    }

    public static function ownPassword(): self
    {
        return new self('Pra trocar a sua própria senha, use o editar perfil.', isSelfChange: true);
    }

    public static function lastAdmin(): self
    {
        return new self('Não dá pra remover o último admin do jogo.');
    }

    public static function quotaForAdmin(): self
    {
        return new self('Admin gera convites sem cota.');
    }
}
