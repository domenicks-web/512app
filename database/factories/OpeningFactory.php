<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Opening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Opening>
 */
class OpeningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serverSeed = Str::random(64);

        return [
            'user_id' => User::factory(),
            'server_seed' => $serverSeed,
            'server_seed_hash' => hash('sha256', $serverSeed),
            'client_seed' => Str::random(32),
            'nonce' => 0,
            'revealed_at' => now(),
        ];
    }
}
