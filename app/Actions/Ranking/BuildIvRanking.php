<?php

declare(strict_types=1);

namespace App\Actions\Ranking;

use App\Models\Specimen;
use App\Models\User;
use App\Services\RankPositions;

/**
 * Ranking pelo maior IV total de um espécime só. Empate no IV divide a
 * posição; o menor mint desse espécime desempata a ordem.
 */
class BuildIvRanking
{
    public function __construct(private readonly RankPositions $positions = new RankPositions) {}

    /**
     * @return list<array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, position: int|null, value: int, specimen: array{speciesName: string, spritePath: string, isShiny: bool, mintNumber: int}|null}>
     */
    public function handle(): array
    {
        $users = User::query()->whereNotNull('nickname')->with('avatarSpecies:id,sprite_path,sprite_shiny_path')->orderBy('id')->get();

        // O grupo é pequeno (só por convite): um espécime por dono, o melhor.
        $best = Specimen::query()
            ->whereIn('user_id', $users->modelKeys())
            ->with('species:id,name,sprite_path,sprite_shiny_path')
            ->orderByDesc('iv_total')
            ->orderBy('mint_number')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $rows = $users->map(function (User $user) use ($best): array {
            $specimen = $best->get($user->id);

            return [
                'userId' => $user->id,
                'nickname' => (string) $user->nickname,
                'tag' => (string) $user->tag,
                'avatarSpritePath' => $user->avatarSpritePath(),
                'value' => $specimen !== null ? (int) $specimen->iv_total : 0,
                'mint' => $specimen?->mint_number ?? PHP_INT_MAX,
                'specimen' => $specimen === null ? null : [
                    'speciesName' => $specimen->species->name,
                    'spritePath' => $specimen->is_shiny ? $specimen->species->sprite_shiny_path : $specimen->species->sprite_path,
                    'isShiny' => $specimen->is_shiny,
                    'mintNumber' => $specimen->mint_number,
                ],
            ];
        })->sort(fn (array $a, array $b): int => [$b['value'], $a['mint'], $a['userId']] <=> [$a['value'], $b['mint'], $b['userId']])
            ->map(function (array $row): array {
                unset($row['mint']);

                return $row;
            })
            ->values()
            ->all();

        return $this->positions->assign($rows);
    }
}
