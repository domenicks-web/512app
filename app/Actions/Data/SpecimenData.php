<?php

declare(strict_types=1);

namespace App\Actions\Data;

use App\Enums\Nature;
use App\Enums\SizeClass;

final readonly class SpecimenData
{
    public function __construct(
        public string $seed,
        public int $speciesId,
        public int $mintNumber,
        public bool $isShiny,
        public float $sizeRoll,
        public SizeClass $sizeClass,
        public Nature $nature,
        public int $ivHp,
        public int $ivAtk,
        public int $ivDef,
        public int $ivSpa,
        public int $ivSpd,
        public int $ivSpe,
    ) {}

    public function ivTotal(): int
    {
        return $this->ivHp + $this->ivAtk + $this->ivDef + $this->ivSpa + $this->ivSpd + $this->ivSpe;
    }
}
