<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Specimen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Carta de troca: o mínimo pra desenhar a carta com o tratamento da
 * raridade e identificar o espécime.
 *
 * @mixin Specimen
 */
class TradeSpecimenResource extends JsonResource
{
    /**
     * @return array{specimenId: int, speciesId: int, name: string, rarityTier: string, isShiny: bool, spritePath: string, mintNumber: int, ivTotal: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'specimenId' => $this->id,
            'speciesId' => $this->species_id,
            'name' => $this->species->name,
            'rarityTier' => $this->species->rarity_tier->value,
            'isShiny' => $this->is_shiny,
            'spritePath' => (string) ($this->is_shiny ? $this->species->sprite_shiny_path : $this->species->sprite_path),
            'mintNumber' => $this->mint_number,
            'ivTotal' => $this->iv_total,
        ];
    }
}
