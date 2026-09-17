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
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $birthdate
 * @property int|null $avatar_species_id
 * @property bool $is_admin
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

    public function hasCompletedProfile(): bool
    {
        return $this->nickname !== null;
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
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'nonce' => 'integer',
            'next_pack_at' => 'datetime',
        ];
    }
}
