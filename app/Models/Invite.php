<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InviteStatus;
use Database\Factories\InviteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $created_by
 * @property int $max_uses
 * @property int $uses_count
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon $created_at
 */
#[Fillable(['code', 'created_by', 'max_uses', 'expires_at'])]
class Invite extends Model
{
    /** @use HasFactory<InviteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * @return HasMany<InviteRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(InviteRedemption::class);
    }

    public function hasUsesLeft(): bool
    {
        return $this->uses_count < $this->max_uses;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isRedeemable(): bool
    {
        return $this->status() === InviteStatus::Active;
    }

    /**
     * Derivado, nunca gravado. Revogado vence esgotado, que vence expirado
     * (ordem do handoff do admin).
     */
    public function status(): InviteStatus
    {
        return match (true) {
            $this->isRevoked() => InviteStatus::Revoked,
            ! $this->hasUsesLeft() => InviteStatus::Exhausted,
            $this->isExpired() => InviteStatus::Expired,
            default => InviteStatus::Active,
        };
    }

    /**
     * A mesma regra do status() em SQL: só os convites que aceitam resgate.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->whereColumn('uses_count', '<', 'max_uses')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
