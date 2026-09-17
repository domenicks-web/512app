<?php

declare(strict_types=1);

namespace App\Actions\Data;

use Carbon\CarbonInterface;

final readonly class CompleteOnboardingData
{
    public function __construct(
        public string $nickname,
        public CarbonInterface $birthdate,
        public int $avatarSpeciesId,
    ) {}
}
