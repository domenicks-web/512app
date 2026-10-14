<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Specimen;
use App\Models\SpecimenTransfer;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecimenTransfer>
 */
class SpecimenTransferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'specimen_id' => Specimen::factory(),
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'trade_id' => Trade::factory(),
            'transferred_at' => now(),
        ];
    }
}
