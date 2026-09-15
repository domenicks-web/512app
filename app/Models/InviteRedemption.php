<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invite_id
 * @property int $user_id
 * @property Carbon $created_at
 */
#[Fillable(['invite_id', 'user_id'])]
class InviteRedemption extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Invite, $this>
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(Invite::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
