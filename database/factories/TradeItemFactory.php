<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TradeItemSide;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\TradeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeItem>
 */
class TradeItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trade_id' => Trade::factory(),
            'specimen_id' => Specimen::factory(),
            'side' => TradeItemSide::Offered,
        ];
    }
}
