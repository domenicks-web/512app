<?php

declare(strict_types=1);

namespace App\Enums;

/** Suspensão tem prazo e devolve tudo; banimento é permanente e liberta os espécimes. */
enum SanctionType: string
{
    case Suspension = 'suspension';
    case Ban = 'ban';
}
