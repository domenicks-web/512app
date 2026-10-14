<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Species;
use App\Models\User;

/**
 * A Pokédex do jogador: as 151 espécies em ordem de dex, cada uma com
 * quantos espécimes ele tem, quantos são shiny, o menor mint e o maior IV
 * (o que a carta destaca) e se algum espécime atual veio de troca.
 * Espécie sem espécime aparece como silhueta no front.
 */
class BuildCollection
{
    /**
     * @return array{
     *     species: array<int, array{id: int, name: string, rarityTier: string, spritePath: string, spriteShinyPath: string, specimenCount: int, shinyCount: int, bestMint: int|null, bestIv: int|null, fromTrade: bool}>,
     *     ownedSpecies: int,
     *     totalSpecimens: int,
     * }
     */
    public function handle(User $user): array
    {
        $owned = $user->specimens()
            ->selectRaw('species_id, count(*) as specimen_count, sum(case when is_shiny then 1 else 0 end) as shiny_count, min(mint_number) as best_mint, max(iv_total) as best_iv')
            ->groupBy('species_id')
            ->get()
            ->keyBy('species_id');

        $fromTrade = $user->specimens()
            ->whereHas('transfers', fn ($query) => $query->where('to_user_id', $user->id))
            ->distinct()
            ->pluck('species_id')
            ->flip();

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
                'bestMint' => $owned->has($species->id) ? (int) $owned->get($species->id)->getAttribute('best_mint') : null,
                'bestIv' => $owned->has($species->id) ? (int) $owned->get($species->id)->getAttribute('best_iv') : null,
                'fromTrade' => $fromTrade->has($species->id),
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
