<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TradeItemSide;
use Database\Factories\TradeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $trade_id
 * @property int $specimen_id
 * @property TradeItemSide $side
 */
#[Fillable(['trade_id', 'specimen_id', 'side'])]
class TradeItem extends Model
{
    /** @use HasFactory<TradeItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<Trade, $this>
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * @return BelongsTo<Specimen, $this>
     */
    public function specimen(): BelongsTo
    {
        return $this->belongsTo(Specimen::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'side' => TradeItemSide::class,
        ];
    }
}
