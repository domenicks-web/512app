<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Nature;
use App\Enums\SizeClass;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Specimen>
 */
class SpecimenFactory extends Factory
{
    protected static int $nextMintNumber = 1;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opening_id' => Opening::factory(),
            'user_id' => User::factory(),
            'species_id' => Species::factory(),
            'seed' => Str::random(64),
            'is_shiny' => false,
            'size_roll' => fake()->randomFloat(5, 0, 1),
            'size_class' => SizeClass::M,
            'nature' => fake()->randomElement(Nature::cases()),
            'iv_hp' => fake()->numberBetween(0, 31),
            'iv_atk' => fake()->numberBetween(0, 31),
            'iv_def' => fake()->numberBetween(0, 31),
            'iv_spa' => fake()->numberBetween(0, 31),
            'iv_spd' => fake()->numberBetween(0, 31),
            'iv_spe' => fake()->numberBetween(0, 31),
            'mint_number' => self::$nextMintNumber++,
            'hash' => Str::random(64),
            'nickname' => null,
            'caught_at' => now(),
        ];
    }
}
