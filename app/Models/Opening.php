<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\RevealedScope;
use Database\Factories\OpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $server_seed
 * @property string $server_seed_hash
 * @property string $client_seed
 * @property int $nonce
 * @property int $opened_slots
 * @property Carbon|null $revealed_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'server_seed', 'server_seed_hash', 'client_seed', 'nonce', 'opened_slots', 'revealed_at'])]
class Opening extends Model
{
    /** @use HasFactory<OpeningFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Os 5 espécimes do pack, inclusive os de bola ainda fechada.
     *
     * @return HasMany<Specimen, $this>
     */
    public function specimens(): HasMany
    {
        return $this->hasMany(Specimen::class)->withoutGlobalScope(RevealedScope::class);
    }

    public function isSlotOpened(int $slot): bool
    {
        return ($this->opened_slots & (1 << ($slot - 1))) !== 0;
    }

    /** Todas as bolas abertas: o pack terminou e a server seed pode ser revelada. */
    public function isComplete(): bool
    {
        return $this->opened_slots === (1 << (int) config('game.pack.slots')) - 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nonce' => 'integer',
            'opened_slots' => 'integer',
            'revealed_at' => 'datetime',
        ];
    }
}
