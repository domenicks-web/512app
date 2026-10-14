<?php

declare(strict_types=1);

namespace App\Actions\Trainer;

use App\Models\Specimen;

/**
 * Dois espécimes lado a lado, como a tela de status dos jogos: em cada IV
 * e no total ganha o maior; no mint ganha o menor. O placar conta os 7
 * (os 6 IVs e o total). Pura: só lê os dois que recebe.
 */
class CompareSpecimens
{
    private const STATS = [
        'ivTotal' => 'iv_total',
        'ivHp' => 'iv_hp',
        'ivAtk' => 'iv_atk',
        'ivDef' => 'iv_def',
        'ivSpa' => 'iv_spa',
        'ivSpd' => 'iv_spd',
        'ivSpe' => 'iv_spe',
    ];

    /**
     * @return array{
     *     stats: list<array{key: string, mine: int, theirs: int, winner: 'mine'|'theirs'|'tie'}>,
     *     mint: array{mine: int, theirs: int, winner: 'mine'|'theirs'|'tie'},
     *     info: list<array{key: string, mine: string, theirs: string, mineNote: string|null, theirsNote: string|null}>,
     *     score: array{mine: int, theirs: int, total: int},
     * }
     */
    public function handle(Specimen $mine, Specimen $theirs): array
    {
        $stats = [];

        foreach (self::STATS as $key => $column) {
            $a = (int) $mine->getAttribute($column);
            $b = (int) $theirs->getAttribute($column);
            $stats[] = ['key' => $key, 'mine' => $a, 'theirs' => $b, 'winner' => $this->winner($a <=> $b)];
        }

        $winners = array_count_values(array_column($stats, 'winner'));

        return [
            'stats' => $stats,
            'mint' => [
                'mine' => $mine->mint_number,
                'theirs' => $theirs->mint_number,
                'winner' => $this->winner($theirs->mint_number <=> $mine->mint_number),
            ],
            'info' => [
                ['key' => 'size', 'mine' => strtoupper($mine->size_class->value), 'theirs' => strtoupper($theirs->size_class->value), 'mineNote' => null, 'theirsNote' => null],
                ['key' => 'nature', 'mine' => $mine->nature->label(), 'theirs' => $theirs->nature->label(), 'mineNote' => $mine->nature->effect(), 'theirsNote' => $theirs->nature->effect()],
            ],
            'score' => [
                'mine' => $winners['mine'] ?? 0,
                'theirs' => $winners['theirs'] ?? 0,
                'total' => count($stats),
            ],
        ];
    }

    /** @return 'mine'|'theirs'|'tie' */
    private function winner(int $comparison): string
    {
        return match ($comparison) {
            1 => 'mine',
            -1 => 'theirs',
            default => 'tie',
        };
    }
}
