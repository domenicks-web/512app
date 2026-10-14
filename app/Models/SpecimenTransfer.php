<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SpecimenTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma passagem de dono de um espécime, por troca. A captura não fica aqui:
 * vem da própria abertura.
 *
 * @property int $id
 * @property int $specimen_id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property int $trade_id
 * @property Carbon $transferred_at
 */
#[Fillable(['specimen_id', 'from_user_id', 'to_user_id', 'trade_id', 'transferred_at'])]
class SpecimenTransfer extends Model
{
    /** @use HasFactory<SpecimenTransferFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['transferred_at' => 'datetime'];
    }
}
