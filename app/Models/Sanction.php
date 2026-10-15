<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SanctionType;
use Database\Factories\SanctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Suspensão ou banimento de um jogador, com histórico. Ativa = não
 * encerrada pelo admin e (banimento ou prazo no futuro); a suspensão
 * vence sozinha, sem agendador.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $issued_by
 * @property SanctionType $type
 * @property string $reason
 * @property Carbon|null $ends_at
 * @property Carbon|null $lifted_at
 * @property int|null $lifted_by
 */
#[Fillable(['user_id', 'issued_by', 'type', 'reason', 'ends_at', 'lifted_at', 'lifted_by'])]
class Sanction extends Model
{
    /** @use HasFactory<SanctionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Sanction>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at')->where(fn (Builder $q) => $q
            ->where('type', SanctionType::Ban)
            ->orWhere('ends_at', '>', now()));
    }

    /** O texto que o jogador vê no login. */
    public function message(): string
    {
        return $this->type === SanctionType::Ban
            ? "Sua conta foi banida. Motivo: {$this->reason}"
            : 'Sua conta está suspensa até '.$this->ends_at?->timezone((string) config('app.timezone'))->format('d/m \à\s H:i').". Motivo: {$this->reason}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SanctionType::class,
            'ends_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }
}
