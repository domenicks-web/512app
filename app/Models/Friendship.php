<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FriendshipStatus;
use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pedido de amizade (`pending`) ou amizade (`accepted`) entre dois
 * jogadores. O par é único nas duas direções — garantido pelas Actions de
 * `app/Actions/Friends/`. Recusar, cancelar e desfazer apagam a linha.
 *
 * @property int $id
 * @property int $requester_id
 * @property int $addressee_id
 * @property FriendshipStatus $status
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 */
#[Fillable(['requester_id', 'addressee_id', 'status', 'accepted_at'])]
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /** O outro lado da amizade, visto por `$user`. */
    public function otherThan(User $user): User
    {
        return $this->requester_id === $user->id ? $this->addressee : $this->requester;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInvolving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeBetween(Builder $query, User $a, User $b): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $pair) => $pair->where('requester_id', $a->id)->where('addressee_id', $b->id))
            ->orWhere(fn (Builder $pair) => $pair->where('requester_id', $b->id)->where('addressee_id', $a->id)));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAccepted(Builder $query): void
    {
        $query->where('status', FriendshipStatus::Accepted);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', FriendshipStatus::Pending);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
            'accepted_at' => 'datetime',
        ];
    }
}
