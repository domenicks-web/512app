<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Data\SpecimenData;
use App\Enums\Nature;
use App\Enums\SizeClass;
use App\Models\Species;

/**
 * Resolve os atributos de um espécime a partir de uma seed já determinada.
 *
 * Função pura: nunca chama rand(), random_bytes(), now() nem toca no banco.
 * Toda a aleatoriedade vem inteiramente da seed recebida, fatiada sempre na
 * mesma ordem (ver SPEC.md, seção 6). A escolha da espécie e o número de
 * mint já vêm prontos de fora; esta classe só decide shiny, tamanho,
 * natureza e IVs.
 */
class ResolveSpecimen
{
    /**
     * Bytes 0 a 3 da seed são reservados pro roll de espécie, decidido antes
     * desta função ser chamada (RarityTable + escolha da espécie).
     */
    private const SHINY_OFFSET = 4;

    private const SIZE_OFFSET = 8;

    private const NATURE_OFFSET = 12;

    private const IV_OFFSET = 16;

    public function handle(string $seed, Species $species, int $mintNumber): SpecimenData
    {
        $bytes = (string) hex2bin($seed);

        $shinyRoll = $this->uniformFromBytes($bytes, self::SHINY_OFFSET, 4);
        $sizeUniform = $this->uniformFromBytes($bytes, self::SIZE_OFFSET, 4);
        $natureRoll = $this->uintFromBytes($bytes, self::NATURE_OFFSET, 4);

        $natureCases = Nature::cases();
        $ivs = [];

        for ($i = 0; $i < 6; $i++) {
            $ivs[] = $this->uintFromBytes($bytes, self::IV_OFFSET + $i * 2, 2) % 32;
        }

        $sizeRoll = $this->resolveSizeRoll($sizeUniform);

        return new SpecimenData(
            seed: $seed,
            speciesId: $species->id,
            mintNumber: $mintNumber,
            isShiny: $shinyRoll < (float) config('game.shiny.rate'),
            sizeRoll: $sizeRoll,
            sizeClass: $this->resolveSizeClass($sizeRoll),
            nature: $natureCases[$natureRoll % count($natureCases)],
            ivHp: $ivs[0],
            ivAtk: $ivs[1],
            ivDef: $ivs[2],
            ivSpa: $ivs[3],
            ivSpd: $ivs[4],
            ivSpe: $ivs[5],
        );
    }

    private function resolveSizeRoll(float $uniform): float
    {
        $mean = (float) config('game.size.mean');
        $stddev = (float) config('game.size.stddev');

        $z = $this->inverseNormalCdf($uniform);

        return min(max($mean + $z * $stddev, 0.0), 1.0);
    }

    private function resolveSizeClass(float $sizeRoll): SizeClass
    {
        /** @var array<string, array{0: float, 1: float}> $ranges */
        $ranges = config('game.size.ranges');
        $last = array_key_last($ranges);

        foreach ($ranges as $class => [, $max]) {
            if ($class === $last || $sizeRoll < $max) {
                return SizeClass::from($class);
            }
        }

        return SizeClass::from($last);
    }

    private function uintFromBytes(string $bytes, int $offset, int $length): int
    {
        $chunk = substr($bytes, $offset, $length);
        $format = $length === 4 ? 'N' : 'n';

        /** @var array{1: int} $unpacked */
        $unpacked = unpack($format, $chunk);

        return $unpacked[1];
    }

    private function uniformFromBytes(string $bytes, int $offset, int $length): float
    {
        return $this->uintFromBytes($bytes, $offset, $length) / 0xFFFFFFFF;
    }

    /**
     * Aproximação racional de Peter Acklam pra inversa da CDF normal
     * padrão. Converte um uniforme em (0, 1) num valor de uma normal
     * padrão, com erro relativo abaixo de 1.15e-9. Não é número mágico do
     * jogo, é constante matemática do algoritmo.
     */
    private function inverseNormalCdf(float $p): float
    {
        $p = min(max($p, 1e-12), 1 - 1e-12);

        $a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02, 1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
        $b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02, 6.680131188771972e+01, -1.328068155288572e+01];
        $c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00, -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
        $d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00, 3.754408661907416e+00];

        $pLow = 0.02425;
        $pHigh = 1 - $pLow;

        if ($p < $pLow) {
            $q = sqrt(-2 * log($p));

            return ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
                / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
        }

        if ($p <= $pHigh) {
            $q = $p - 0.5;
            $r = $q * $q;

            return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q
                / ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1);
        }

        $q = sqrt(-2 * log(1 - $p));

        return -((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
            / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
    }
}
