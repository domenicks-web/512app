<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PokemonType;
use App\Enums\RarityTier;
use Database\Factories\SpeciesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property PokemonType $type_1
 * @property PokemonType|null $type_2
 * @property RarityTier $rarity_tier
 * @property int $base_stat_total
 * @property int $evolution_stage
 * @property string $base_height_m
 * @property string $base_weight_kg
 * @property string $sprite_path
 * @property string $sprite_shiny_path
 * @property string $artwork_path
 * @property string|null $flavor_pt
 */
#[Fillable([
    'id',
    'name',
    'slug',
    'type_1',
    'type_2',
    'rarity_tier',
    'base_stat_total',
    'evolution_stage',
    'base_height_m',
    'base_weight_kg',
    'sprite_path',
    'sprite_shiny_path',
    'artwork_path',
    'flavor_pt',
])]
class Species extends Model
{
    /** @use HasFactory<SpeciesFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @return HasMany<Specimen, $this>
     */
    public function specimens(): HasMany
    {
        return $this->hasMany(Specimen::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type_1' => PokemonType::class,
            'type_2' => PokemonType::class,
            'rarity_tier' => RarityTier::class,
            'base_height_m' => 'decimal:2',
            'base_weight_kg' => 'decimal:2',
        ];
    }
}
