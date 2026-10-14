<?php

declare(strict_types=1);

namespace App\Actions\Data;

use Carbon\CarbonInterface;

final readonly class CreateInviteData
{
    /**
     * @param  string  $prefix  já validado: `[A-Z0-9]`, até 8 caracteres
     * @param  string|null  $suffix  o sufixo mostrado na prévia; null sorteia um
     */
    public function __construct(
        public string $prefix,
        public ?string $suffix,
        public int $maxUses,
        public ?CarbonInterface $expiresAt,
    ) {}
}
