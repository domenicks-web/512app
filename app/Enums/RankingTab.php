<?php

declare(strict_types=1);

namespace App\Enums;

enum RankingTab: string
{
    case Species = 'especies';
    case Shinies = 'shinies';
    case Iv = 'iv';
    case FirstMints = 'primeiros';
}
