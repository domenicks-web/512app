<?php

declare(strict_types=1);

namespace App\Actions\Ranking;

use App\Models\Specimen;
use App\Models\User;
use App\Services\RankPositions;

/** Ranking por primeiros do servidor (mint 1 da espécie); espécies distintas só desempatam a ordem. */
class BuildFirstMintRanking
{
    public function __construct(private readonly RankPositions $positions = new RankPositions) {}

    /**
     * @return list<array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, position: int|null, value: int, specimen: null}>
     */
    public function handle(): array
    {
        $users = User::query()
            ->active()
            ->whereNotNull('nickname')
            ->with('avatarSpecies:id,sprite_path,sprite_shiny_path')
            ->select('users.*')
            ->addSelect([
                'first_mints' => Specimen::query()->selectRaw('count(*)')->whereColumn('user_id', 'users.id')->where('mint_number', (int) config('game.ranking.first_mint')),
                'owned_species' => Specimen::query()->selectRaw('count(distinct species_id)')->whereColumn('user_id', 'users.id'),
            ])
            ->orderByDesc('first_mints')
            ->orderByDesc('owned_species')
            ->orderBy('id')
            ->get();

        $values = array_values($users->map(fn (User $user): int => (int) $user->getAttribute('first_mints'))->all());
        $positions = $this->positions->assign($values);

        $rows = [];

        foreach ($users as $index => $user) {
            $rows[] = [
                'userId' => $user->id,
                'nickname' => (string) $user->nickname,
                'tag' => (string) $user->tag,
                'avatarSpritePath' => $user->avatarSpritePath(),
                'position' => $positions[$index],
                'value' => $values[$index],
                'specimen' => null,
            ];
        }

        return $rows;
    }
}
