<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SanctionType;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sanction>
 */
class SanctionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => SanctionType::Suspension,
            'reason' => 'Comportamento ruim no grupo',
            'ends_at' => now()->addDays(3),
        ];
    }

    public function ban(): static
    {
        return $this->state(['type' => SanctionType::Ban, 'ends_at' => null]);
    }
}
