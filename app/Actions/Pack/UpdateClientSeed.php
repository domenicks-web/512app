<?php

declare(strict_types=1);

namespace App\Actions\Pack;

use App\Models\User;

/** Troca a client seed (vale a partir do próximo pack). O formato é checado no FormRequest. */
class UpdateClientSeed
{
    public function handle(User $user, string $clientSeed): User
    {
        $user->forceFill(['client_seed' => $clientSeed])->save();

        return $user;
    }
}
