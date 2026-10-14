<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RarityTier;
use App\Models\Specimen;
use App\Models\User;

/**
 * O espécime "de maior valor" do jogador, pro destaque da Home: shiny
 * primeiro, depois a raridade da espécie, o IV total e, no empate, o menor
 * número de mint.
 */
class FindBestSpecimen
{
    private const TIER_RANK = [
        RarityTier::Legendary->value => 4,
        RarityTier::Rare->value => 3,
        RarityTier::Uncommon->value => 2,
        RarityTier::Common->value => 1,
    ];

    /**
     * @return array{speciesName: string, spritePath: string, isShiny: bool, rarityTier: string, mintNumber: int}|null
     */
    public function handle(User $user): ?array
    {
        $best = $user->specimens()
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
            ->first();

        if ($best === null) {
            return null;
        }

        return [
            'speciesName' => $best->species->name,
            'spritePath' => $best->is_shiny ? $best->species->sprite_shiny_path : $best->species->sprite_path,
            'isShiny' => $best->is_shiny,
            'rarityTier' => $best->species->rarity_tier->value,
            'mintNumber' => $best->mint_number,
        ];
    }
}
