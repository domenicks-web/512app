<?php

declare(strict_types=1);

namespace App\Actions\Data;

use App\Enums\RarityTier;

/** Um slot sorteado: a seed dele, o tier e a espécie. O resto sai do ResolveSpecimen. */
final readonly class DrawnSlot
{
    public function __construct(
        public int $slot,
        public string $seed,
        public RarityTier $tier,
        public int $speciesId,
    ) {}
}
