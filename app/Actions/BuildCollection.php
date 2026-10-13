<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Species;
use App\Models\User;

/**
 * A Pokédex do jogador: as 151 espécies em ordem de dex, cada uma com
 * quantos espécimes ele tem e quantos deles são shiny. Espécie sem
 * espécime aparece como silhueta no front.
 */
class BuildCollection
{
    /**
     * @return array{
     *     species: list<array{id: int, name: string, rarityTier: string, spritePath: string, spriteShinyPath: string, specimenCount: int, shinyCount: int}>,
     *     ownedSpecies: int,
     *     totalSpecimens: int,
     * }
     */
    public function handle(User $user): array
    {
        $owned = $user->specimens()
            ->selectRaw('species_id, count(*) as specimen_count, sum(case when is_shiny then 1 else 0 end) as shiny_count')
            ->groupBy('species_id')
            ->get()
            ->keyBy('species_id');

        $species = Species::query()
            ->orderBy('id')
            ->get(['id', 'name', 'rarity_tier', 'sprite_path', 'sprite_shiny_path'])
            ->map(fn (Species $species): array => [
                'id' => $species->id,
                'name' => $species->name,
                'rarityTier' => $species->rarity_tier->value,
                'spritePath' => $species->sprite_path,
                'spriteShinyPath' => $species->sprite_shiny_path,
                'specimenCount' => (int) $owned->get($species->id)?->getAttribute('specimen_count'),
                'shinyCount' => (int) $owned->get($species->id)?->getAttribute('shiny_count'),
            ])
            ->values()
            ->all();

        return [
            'species' => $species,
            'ownedSpecies' => $owned->count(),
            'totalSpecimens' => (int) $owned->sum('specimen_count'),
        ];
    }
}
