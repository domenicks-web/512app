<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Posição de ranking: as linhas já vêm ordenadas; mesmo `value` divide a
 * posição (1º, 1º, 3º) e `value` 0 fica sem posição (`null`).
 */
class RankPositions
{
    /**
     * @template T of array{value: int}
     *
     * @param  list<T>  $rows
     * @return list<T&array{position: int|null}>
     */
    public function assign(array $rows): array
    {
        $previous = null;
        $position = 0;

        foreach ($rows as $index => $row) {
            if ($row['value'] !== $previous) {
                $position = $index + 1;
                $previous = $row['value'];
            }

            $rows[$index]['position'] = $row['value'] > 0 ? $position : null;
        }

        return $rows;
    }
}
