<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\GroupPresence;
use Carbon\CarbonInterface;

/**
 * Todo mundo do grupo (o jogo é fechado por convite, então "amigos" é
 * quem já passou pelo onboarding), com online primeiro e o resto por
 * nickname. Cada um traz o `nickname#tag` completo, que é como o SPEC
 * identifica um jogador fora do jogo.
 */
class BuildGroupRoster
{
    public function __construct(
        private readonly BuildSpeciesRanking $ranking,
        private readonly GroupPresence $presence,
    ) {}

    /**
     * @return list<array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, ownedSpecies: int, isOnline: bool, isYou: bool}>
     */
    public function handle(User $viewer, CarbonInterface $now): array
    {
        $online = $this->presence->onlineUserIds($now);

        return collect($this->ranking->handle())
            ->map(fn (array $row): array => [
                'userId' => $row['userId'],
                'nickname' => $row['nickname'],
                'tag' => $row['tag'],
                'avatarSpritePath' => $row['avatarSpritePath'],
                'ownedSpecies' => $row['ownedSpecies'],
                'isOnline' => $row['userId'] === $viewer->id || in_array($row['userId'], $online, true),
                'isYou' => $row['userId'] === $viewer->id,
            ])
            ->sort(fn (array $a, array $b): int => [$b['isOnline'], mb_strtolower($a['nickname'])]
                <=> [$a['isOnline'], mb_strtolower($b['nickname'])])
            ->values()
            ->all();
    }
}
