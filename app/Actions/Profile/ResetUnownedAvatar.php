<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;

/**
 * Depois que espécimes saem da coleção do jogador (troca, fase 4): se o
 * avatar era da coleção e ele não tem mais aquele espécime, volta pro
 * primeiro parceiro do config (Pikachu).
 */
class ResetUnownedAvatar
{
    public function __construct(private readonly ListAvatarOptions $options) {}

    public function handle(User $user): void
    {
        if ($user->avatar_species_id === null || $this->options->allows($user, $user->avatar_species_id, $user->avatar_is_shiny)) {
            return;
        }

        $user->update([
            'avatar_species_id' => config('game.onboarding.avatar_species_ids')[0],
            'avatar_is_shiny' => false,
        ]);
    }
}
