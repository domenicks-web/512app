<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TradeStatus;
use Database\Factories\TradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property TradeStatus $status
 * @property string|null $message
 * @property Carbon|null $responded_at
 */
#[Fillable(['from_user_id', 'to_user_id', 'status', 'message', 'responded_at'])]
class Trade extends Model
{
    /** @use HasFactory<TradeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return HasMany<TradeItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TradeItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TradeStatus::class,
            'responded_at' => 'datetime',
        ];
    }
}
