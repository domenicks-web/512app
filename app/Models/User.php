<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
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
 * @property bool $avatar_is_shiny
 * @property bool $is_admin
 * @property bool $is_system
 * @property int $invite_quota
 * @property int|null $invited_by
 * @property string $password
 * @property string|null $remember_token
 * @property string $client_seed
 * @property int $nonce
 * @property string|null $next_server_seed
 * @property string|null $next_server_seed_hash
 * @property Carbon|null $next_pack_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nickname', 'tag', 'email', 'password', 'birthdate', 'avatar_species_id', 'avatar_is_shiny', 'invited_by', 'client_seed'])]
#[Hidden(['password', 'remember_token', 'next_server_seed'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'avatar_is_shiny' => false,
    ];

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

    /** Sprite do avatar: o shiny quando o parceiro escolhido é a versão shiny. */
    public function avatarSpritePath(): ?string
    {
        return $this->avatar_is_shiny
            ? $this->avatarSpecies?->sprite_shiny_path
            : $this->avatarSpecies?->sprite_path;
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
        $ids = Friendship::query()
            ->accepted()
            ->involving($this)
            ->get(['requester_id', 'addressee_id'])
            ->map(fn (Friendship $friendship): int => $friendship->requester_id === $this->id
                ? $friendship->addressee_id
                : $friendship->requester_id);

        // Amigo suspenso não conta enquanto a sanção durar.
        return array_values(self::query()->active()->whereKey($ids)->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * @return HasMany<Sanction, $this>
     */
    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    public function activeSanction(): ?Sanction
    {
        return $this->sanctions()->active()->latest('id')->first();
    }

    /**
     * Jogadores que o grupo vê: sem a conta do sistema e sem sanção ativa.
     *
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_system', false)->whereDoesntHave('sanctions', fn (Builder $q) => $q->active());
    }

    /** Visível pro grupo: não é o sistema e não tem sanção ativa. */
    public function isActive(): bool
    {
        return ! $this->is_system && $this->activeSanction() === null;
    }

    /** Professor Carvalho, quem guarda os espécimes libertados. */
    public static function system(): self
    {
        return self::query()->where('is_system', true)->firstOrFail();
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
            'is_system' => 'boolean',
            'avatar_is_shiny' => 'boolean',
            'invite_quota' => 'integer',
            'password' => 'hashed',
            'nonce' => 'integer',
            'next_pack_at' => 'datetime',
            'next_server_seed' => 'encrypted',
        ];
    }
}
