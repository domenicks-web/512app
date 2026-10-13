<?php

declare(strict_types=1);

namespace App\Actions\Data;

use Carbon\CarbonInterface;

final readonly class CreateInviteData
{
    /**
     * @param  int|null  $maxUses  null = usos infinitos
     */
    public function __construct(
        public ?int $maxUses,
        public ?CarbonInterface $expiresAt,
    ) {}
}
