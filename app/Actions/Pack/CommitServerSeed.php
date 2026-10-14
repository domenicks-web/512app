<?php

declare(strict_types=1);

namespace App\Actions\Pack;

use App\Models\User;
use App\Services\ProvablyFair;

/**
 * Garante que o próximo pack do jogador já tem server seed comprometida:
 * a seed fica guardada (criptografada) e só o hash é mostrado até o pack
 * ser aberto.
 */
class CommitServerSeed
{
    public function __construct(private readonly ProvablyFair $provablyFair = new ProvablyFair) {}

    public function handle(User $user, bool $force = false): User
    {
        if (! $force && $user->next_server_seed !== null && $user->next_server_seed_hash !== null) {
            return $user;
        }

        $seed = $this->provablyFair->generateServerSeed();
        $user->forceFill([
            'next_server_seed' => $seed,
            'next_server_seed_hash' => $this->provablyFair->hash($seed),
        ])->save();

        return $user;
    }
}
