<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invite>
 */
class InviteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)),
            'created_by' => User::factory(),
            'max_uses' => 1,
            'uses_count' => 0,
            'expires_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_uses' => 1,
            'uses_count' => 1,
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_uses' => null,
        ]);
    }
}
