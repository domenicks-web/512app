<?php

declare(strict_types=1);

namespace App\Actions\Pack;

use App\Enums\RarityTier;
use App\Models\Species;

/**
 * As espécies de cada tier em ordem de dex, do jeito que o DrawPack
 * espera. Usado na abertura e na /verificar, pra conta ser a mesma.
 */
class ListSpeciesIdsByTier
{
    /**
     * @return array<string, list<int>>
     */
    public function handle(): array
    {
        $byTier = [];

        foreach (RarityTier::cases() as $tier) {
            $byTier[$tier->value] = array_values(Species::where('rarity_tier', $tier->value)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values()->all());
        }

        return $byTier;
    }
}
