<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $nickname
 * @property string|null $tag
 * @property Carbon|null $nickname_changed_at
 * @property string $email
 * @property string|null $pending_email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $birthdate
 * @property Carbon|null $birthdate_corrected_at
 * @property int|null $avatar_species_id
 * @property bool $is_admin
 * @property int $invite_quota
 * @property int|null $invited_by
 * @property string $password
 * @property string|null $remember_token
 * @property string $client_seed
 * @property int $nonce
 * @property Carbon|null $next_pack_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nickname', 'tag', 'email', 'password', 'birthdate', 'avatar_species_id', 'invited_by', 'client_seed'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsTo<Invite, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Invite::class, 'invited_by');
    }

    /**
     * @return HasMany<Invite, $this>
     */
    public function createdInvites(): HasMany
    {
        return $this->hasMany(Invite::class, 'created_by');
    }

    /**
     * @return HasMany<Opening, $this>
     */
    public function openings(): HasMany
    {
        return $this->hasMany(Opening::class);
    }

    /**
     * @return HasMany<Specimen, $this>
     */
    public function specimens(): HasMany
    {
        return $this->hasMany(Specimen::class);
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function avatarSpecies(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'avatar_species_id');
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'requester_id');
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'addressee_id');
    }

    /**
     * Ids de quem é amigo (amizade aceita), nas duas direções.
     *
     * @return list<int>
     */
    public function friendIds(): array
    {
        return array_values(Friendship::query()
            ->accepted()
            ->involving($this)
            ->get(['requester_id', 'addressee_id'])
            ->map(fn (Friendship $friendship): int => $friendship->requester_id === $this->id
                ? $friendship->addressee_id
                : $friendship->requester_id)
            ->all());
    }

    public function hasCompletedProfile(): bool
    {
        return $this->nickname !== null;
    }

    /** "Lin#4821", ou null enquanto o jogador não passou pelo onboarding. */
    public function nicknameWithTag(): ?string
    {
        return $this->hasCompletedProfile() ? "{$this->nickname}#{$this->tag}" : null;
    }

    /**
     * Acha o jogador por email ou por "Nick#1234" (o nickname sozinho se
     * repete, a tag desempata). Usado pelos comandos de admin.
     */
    public static function findByEmailOrNicknameWithTag(string $identifier): ?self
    {
        if (str_contains($identifier, '@')) {
            return self::where('email', $identifier)->first();
        }

        [$nickname, $tag] = array_pad(explode('#', $identifier, 2), 2, null);

        if ($tag === null || $tag === '') {
            return null;
        }

        return self::where('nickname', $nickname)->where('tag', $tag)->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'birthdate_corrected_at' => 'datetime',
            'nickname_changed_at' => 'datetime',
            'is_admin' => 'boolean',
            'invite_quota' => 'integer',
            'password' => 'hashed',
            'nonce' => 'integer',
            'next_pack_at' => 'datetime',
        ];
    }
}
