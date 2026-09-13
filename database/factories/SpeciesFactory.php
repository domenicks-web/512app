<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PokemonType;
use App\Enums\RarityTier;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Species>
 */
class SpeciesFactory extends Factory
{
    protected static int $nextId = 1;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = self::$nextId++;

        return [
            'id' => $id,
            'name' => $name = fake()->unique()->word().'-'.$id,
            'slug' => Str::slug($name),
            'type_1' => fake()->randomElement(PokemonType::cases()),
            'type_2' => null,
            'rarity_tier' => RarityTier::Common,
            'base_stat_total' => fake()->numberBetween(200, 600),
            'evolution_stage' => fake()->numberBetween(1, 3),
            'base_height_m' => fake()->randomFloat(2, 0.2, 3.0),
            'base_weight_kg' => fake()->randomFloat(2, 1, 200),
            'sprite_path' => "species/{$id}.png",
            'sprite_shiny_path' => "species/{$id}-shiny.png",
            'artwork_path' => "species/{$id}-art.png",
            'flavor_pt' => null,
        ];
    }

    public function legendary(): static
    {
        return $this->state(fn (): array => ['rarity_tier' => RarityTier::Legendary]);
    }

    public function rare(): static
    {
        return $this->state(fn (): array => ['rarity_tier' => RarityTier::Rare]);
    }

    public function uncommon(): static
    {
        return $this->state(fn (): array => ['rarity_tier' => RarityTier::Uncommon]);
    }
}
