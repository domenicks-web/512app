<?php

declare(strict_types=1);

namespace App\Actions\Data;

use Carbon\CarbonInterface;

final readonly class RegisterUserData
{
    public function __construct(
        public string $nickname,
        public string $email,
        public string $password,
        public CarbonInterface $birthdate,
        public string $inviteCode,
    ) {}
}
