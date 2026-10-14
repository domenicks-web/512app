<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Posição de ranking: os valores já vêm ordenados; valores iguais dividem
 * a posição (1º, 1º, 3º) e valor 0 fica sem posição (`null`).
 */
class RankPositions
{
    /**
     * @param  list<int>  $values
     * @return list<int|null>
     */
    public function assign(array $values): array
    {
        $positions = [];
        $previous = null;
        $position = 0;

        foreach ($values as $index => $value) {
            if ($value !== $previous) {
                $position = $index + 1;
                $previous = $value;
            }

            $positions[] = $value > 0 ? $position : null;
        }

        return $positions;
    }
}
