<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TradeCancelReason;
use App\Enums\TradeStatus;
use Database\Factories\TradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property int|null $counter_of_id
 * @property TradeStatus $status
 * @property TradeCancelReason|null $cancel_reason
 * @property string|null $message
 * @property Carbon $expires_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $proposer_seen_at
 * @property Carbon $created_at
 */
#[Fillable(['from_user_id', 'to_user_id', 'counter_of_id', 'status', 'cancel_reason', 'message', 'expires_at', 'responded_at', 'proposer_seen_at'])]
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
     * @return BelongsTo<Trade, $this>
     */
    public function counterOf(): BelongsTo
    {
        return $this->belongsTo(Trade::class, 'counter_of_id');
    }

    /**
     * Pendente e dentro da validade: a única que dá pra aceitar, recusar
     * ou contrapropor. "Expirada" não é status gravado, é esta conta.
     *
     * @param  Builder<Trade>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', TradeStatus::Pending)->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<Trade>  $query
     */
    public function scopeInvolving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id));
    }

    /**
     * Desfecho que quem propôs ainda não viu: aceita (some quando a
     * cerimônia toca), recusada ou caída porque um espécime foi trocado
     * em outra (somem quando ele abre a aba delas).
     *
     * @param  Builder<Trade>  $query
     */
    public function scopeOutcomeUnseenBy(Builder $query, User $user): void
    {
        $query->where('from_user_id', $user->id)
            ->whereNull('proposer_seen_at')
            ->where(fn (Builder $q) => $q
                ->whereIn('status', [TradeStatus::Accepted, TradeStatus::Declined])
                ->orWhere(fn (Builder $c) => $c->where('status', TradeStatus::Cancelled)->where('cancel_reason', TradeCancelReason::Unavailable)));
    }

    public function isOutcomeUnseenBy(User $user): bool
    {
        return $this->from_user_id === $user->id
            && $this->proposer_seen_at === null
            && ($this->status === TradeStatus::Accepted
                || $this->status === TradeStatus::Declined
                || ($this->status === TradeStatus::Cancelled && $this->cancel_reason === TradeCancelReason::Unavailable));
    }

    public function isOpen(): bool
    {
        return $this->status === TradeStatus::Pending && $this->expires_at->isFuture();
    }

    /** @return 'open'|'expired'|'cancelled'|'accepted'|'declined' */
    public function state(): string
    {
        return match ($this->status) {
            TradeStatus::Pending => $this->isOpen() ? 'open' : 'expired',
            TradeStatus::Accepted => 'accepted',
            TradeStatus::Declined => 'declined',
            TradeStatus::Cancelled => 'cancelled',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TradeStatus::class,
            'cancel_reason' => TradeCancelReason::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'proposer_seen_at' => 'datetime',
        ];
    }
}
