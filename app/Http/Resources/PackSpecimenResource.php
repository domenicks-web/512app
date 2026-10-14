<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Nature;
use App\Models\Specimen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um espécime como a abertura do pack encena: só sai do servidor depois
 * que a bola dele é aberta. Tudo já decidido; o cliente só anima.
 *
 * @mixin Specimen
 */
class PackSpecimenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $species = $this->species;
        $sizeRoll = (float) $this->size_roll;
        $natureIndex = (int) array_search($this->nature, Nature::cases(), true);
        $staticSprite = $this->is_shiny ? $species->sprite_shiny_path : $species->sprite_path;
        $animatedSprite = $this->is_shiny ? $species->sprite_animated_shiny_path : $species->sprite_animated_path;

        return [
            'id' => $this->id,
            'slot' => $this->slot,
            'speciesId' => $species->id,
            'dex' => str_pad((string) $species->id, 3, '0', STR_PAD_LEFT),
            'name' => $species->name,
            'tier' => $species->rarity_tier->value,
            'isShiny' => $this->is_shiny,
            'mint' => $this->mint_number,
            'sprite' => '/storage/'.$staticSprite,
            'spriteAnimated' => $animatedSprite !== null ? '/storage/'.$animatedSprite : null,
            'sizeRoll' => $sizeRoll,
            'sizeClass' => $this->size_class->value,
            'sizePercentile' => $this->sizePercentile($sizeRoll),
            'heightM' => round((float) $species->base_height_m * (0.7 + $sizeRoll * 0.6), 2),
            'nature' => $this->nature->label(),
            'natureIndex' => $natureIndex,
            'ivs' => [$this->iv_hp, $this->iv_atk, $this->iv_def, $this->iv_spa, $this->iv_spd, $this->iv_spe],
            'ivTotal' => $this->iv_hp + $this->iv_atk + $this->iv_def + $this->iv_spa + $this->iv_spd + $this->iv_spe,
            'seed' => $this->seed,
            'hash' => $this->hash,
            'caughtAt' => $this->caught_at->toIso8601String(),
            'holo' => $this->holo($this->seed),
        ];
    }

    /**
     * Quantos % dos da espécie este é maior (1–99), pela CDF da normal do
     * tamanho (μ e σ do config).
     */
    private function sizePercentile(float $sizeRoll): int
    {
        $z = ($sizeRoll - (float) config('game.size.mean')) / (float) config('game.size.stddev');

        return (int) max(1, min(99, round(50 * (1 + $this->erf($z / M_SQRT2)))));
    }

    /** Aproximação de Abramowitz e Stegun (7.1.26), erro abaixo de 1.5e-7. */
    private function erf(float $x): float
    {
        $sign = $x < 0 ? -1 : 1;
        $x = abs($x);
        $t = 1 / (1 + 0.3275911 * $x);
        $y = 1 - (((((1.061405429 * $t - 1.453152027) * $t) + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);

        return $sign * $y;
    }

    /**
     * Brilho da carta, só visual: bytes 29–31 da seed do espécime nas
     * faixas do config.
     *
     * @return array{angle: int, offset: int, intensity: float}
     */
    private function holo(string $seed): array
    {
        $byte = fn (int $index): int => (int) hexdec(substr($seed, $index * 2, 2));
        $range = fn (string $key, int $index): float => (float) config("game.pack.holo.{$key}.0")
            + $byte($index) / 255 * ((float) config("game.pack.holo.{$key}.1") - (float) config("game.pack.holo.{$key}.0"));

        return [
            'angle' => (int) round($range('angle', 30)),
            'offset' => (int) round($range('offset', 31)),
            'intensity' => round($range('intensity', 29), 3),
        ];
    }
}
