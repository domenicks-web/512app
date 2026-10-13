<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InviteStatus;
use Database\Factories\InviteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $created_by
 * @property int|null $max_uses
 * @property int $uses_count
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
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
     * @return HasMany<InviteRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(InviteRedemption::class);
    }

    public function hasUsesLeft(): bool
    {
        return $this->max_uses === null || $this->uses_count < $this->max_uses;
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
     * Revogado vence expirado, que vence esgotado: mostra o motivo mais
     * "definitivo" quando mais de um se aplica.
     */
    public function status(): InviteStatus
    {
        return match (true) {
            $this->isRevoked() => InviteStatus::Revoked,
            $this->isExpired() => InviteStatus::Expired,
            ! $this->hasUsesLeft() => InviteStatus::Exhausted,
            default => InviteStatus::Active,
        };
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
