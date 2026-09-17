<?php

declare(strict_types=1);

namespace App\Actions\Data;

final readonly class RegisterUserData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $inviteCode,
    ) {}
}
