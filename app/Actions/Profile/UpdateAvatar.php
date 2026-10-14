<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;

/** Troca o parceiro do jogador; o FormRequest confere se é uma opção dele. */
class UpdateAvatar
{
    public function handle(User $user, int $speciesId, bool $isShiny): void
    {
        $user->update(['avatar_species_id' => $speciesId, 'avatar_is_shiny' => $isShiny]);
    }
}
