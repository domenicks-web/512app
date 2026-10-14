<?php

declare(strict_types=1);

namespace App\Actions\Ranking;

use App\Actions\BuildSpeciesRanking;
use App\Enums\RankingTab;

/** Escolhe o ranking pela aba; a de espécies vira o mesmo formato das outras. */
class BuildRanking
{
    public function __construct(
        private readonly BuildSpeciesRanking $species,
        private readonly BuildShinyRanking $shinies,
        private readonly BuildIvRanking $iv,
        private readonly BuildFirstMintRanking $firstMints,
    ) {}

    /**
     * @return list<array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, position: int|null, value: int, specimen: array{speciesName: string, spritePath: string, isShiny: bool, mintNumber: int}|null}>
     */
    public function handle(RankingTab $tab): array
    {
        return match ($tab) {
            RankingTab::Species => array_map(fn (array $row): array => [
                'userId' => $row['userId'],
                'nickname' => $row['nickname'],
                'tag' => $row['tag'],
                'avatarSpritePath' => $row['avatarSpritePath'],
                'position' => $row['position'],
                'value' => $row['ownedSpecies'],
                'specimen' => null,
            ], $this->species->handle()),
            RankingTab::Shinies => $this->shinies->handle(),
            RankingTab::Iv => $this->iv->handle(),
            RankingTab::FirstMints => $this->firstMints->handle(),
        };
    }
}
