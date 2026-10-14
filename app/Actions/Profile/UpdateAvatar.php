<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;

/** Troca o parceiro do jogador; a lista válida é checada no FormRequest. */
class UpdateAvatar
{
    public function handle(User $user, int $speciesId): void
    {
        $user->update(['avatar_species_id' => $speciesId]);
    }
}
