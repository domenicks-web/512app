<?php

declare(strict_types=1);

namespace App\Enums;

/** Por que uma proposta foi cancelada: quem propôs desistiu, ou um espécime dela foi trocado em outra. */
enum TradeCancelReason: string
{
    case ByProposer = 'by_proposer';
    case Unavailable = 'unavailable';
}
