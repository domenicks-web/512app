<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Nature;
use App\Enums\SizeClass;
use Database\Factories\SpecimenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $opening_id
 * @property int $user_id
 * @property int $species_id
 * @property string $seed
 * @property bool $is_shiny
 * @property string $size_roll
 * @property SizeClass $size_class
 * @property Nature $nature
 * @property int $iv_hp
 * @property int $iv_atk
 * @property int $iv_def
 * @property int $iv_spa
 * @property int $iv_spd
 * @property int $iv_spe
 * @property int $iv_total
 * @property int $mint_number
 * @property string $hash
 * @property string|null $nickname
 * @property Carbon $caught_at
 */
#[Fillable([
    'opening_id',
    'user_id',
    'species_id',
    'seed',
    'is_shiny',
    'size_roll',
    'size_class',
    'nature',
    'iv_hp',
    'iv_atk',
    'iv_def',
    'iv_spa',
    'iv_spd',
    'iv_spe',
    'mint_number',
    'hash',
    'nickname',
    'caught_at',
])]
class Specimen extends Model
{
    /** @use HasFactory<SpecimenFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Opening, $this>
     */
    public function opening(): BelongsTo
    {
        return $this->belongsTo(Opening::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_shiny' => 'boolean',
            'size_roll' => 'decimal:5',
            'size_class' => SizeClass::class,
            'nature' => Nature::class,
            'caught_at' => 'datetime',
        ];
    }
}
