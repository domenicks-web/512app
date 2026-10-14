<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RarityTier;
use App\Models\Specimen;
use App\Models\User;

/**
 * Os espécimes "de maior valor" do jogador: shiny primeiro, depois a
 * raridade da espécie, o IV total e, no empate, o menor número de mint.
 * É o destaque da Home (o primeiro) e o Time do Cartão de Treinador.
 */
class FindTopSpecimens
{
    private const TIER_RANK = [
        RarityTier::Legendary->value => 4,
        RarityTier::Rare->value => 3,
        RarityTier::Uncommon->value => 2,
        RarityTier::Common->value => 1,
    ];

    /**
     * @return list<array{id: int, speciesId: int, speciesName: string, spritePath: string, isShiny: bool, rarityTier: string, mintNumber: int, ivTotal: int}>
     */
    public function handle(User $user, int $limit): array
    {
        return $user->specimens()
            ->with('species:id,name,rarity_tier,sprite_path,sprite_shiny_path')
            ->get(['id', 'species_id', 'is_shiny', 'iv_total', 'mint_number'])
            ->sort(fn (Specimen $a, Specimen $b): int => [
                $b->is_shiny,
                self::TIER_RANK[$b->species->rarity_tier->value],
                $b->iv_total,
                $a->mint_number,
            ] <=> [
                $a->is_shiny,
                self::TIER_RANK[$a->species->rarity_tier->value],
                $a->iv_total,
                $b->mint_number,
            ])
            ->take($limit)
            ->map(fn (Specimen $specimen): array => [
                'id' => $specimen->id,
                'speciesId' => $specimen->species->id,
                'speciesName' => $specimen->species->name,
                'spritePath' => $specimen->is_shiny ? $specimen->species->sprite_shiny_path : $specimen->species->sprite_path,
                'isShiny' => $specimen->is_shiny,
                'rarityTier' => $specimen->species->rarity_tier->value,
                'mintNumber' => $specimen->mint_number,
                'ivTotal' => (int) $specimen->iv_total,
            ])
            ->values()
            ->all();
    }
}
