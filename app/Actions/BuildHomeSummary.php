<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TradeStatus;
use App\Models\Species;
use App\Models\Trade;
use App\Models\User;
use App\Services\GroupPresence;
use Carbon\CarbonInterface;

/**
 * Monta os números da Home: treinador, coleção, quando sai o próximo pack,
 * posição no ranking de espécies, propostas de troca recebidas, destaque
 * do feed e quantos amigos estão online.
 */
class BuildHomeSummary
{
    public function __construct(
        private readonly BuildSpeciesRanking $ranking,
        private readonly FindFeedHighlight $feed,
        private readonly GroupPresence $presence,
    ) {}

    /**
     * @return array{
     *     trainer: array{nickname: string, tag: string, avatarSpritePath: string|null, isAdmin: bool},
     *     collection: array{ownedSpecies: int, totalSpecies: int},
     *     nextPackAt: string|null,
     *     rankingPosition: int|null,
     *     pendingTrades: int,
     *     feedHighlight: array{isShiny: bool, trainerNickname: string, speciesName: string, mintNumber: int, caughtAgo: string}|null,
     *     friendsOnline: int,
     *     presencePollSeconds: int,
     * }
     */
    public function handle(User $user, CarbonInterface $today): array
    {
        $ownRow = collect($this->ranking->handle())->firstWhere('userId', $user->id);

        return [
            'trainer' => [
                'nickname' => (string) $user->nickname,
                'tag' => (string) $user->tag,
                'avatarSpritePath' => $user->avatarSpritePath(),
                'isAdmin' => $user->is_admin,
            ],
            'collection' => [
                'ownedSpecies' => $user->specimens()->distinct()->count('species_id'),
                'totalSpecies' => Species::count(),
            ],
            'nextPackAt' => $user->next_pack_at !== null && $user->next_pack_at->isAfter($today)
                ? $user->next_pack_at->toIso8601String()
                : null,
            'rankingPosition' => $ownRow['position'] ?? null,
            'pendingTrades' => Trade::query()
                ->where('to_user_id', $user->id)
                ->where('status', TradeStatus::Pending)
                ->count(),
            'feedHighlight' => $this->feed->handle(),
            'friendsOnline' => count(array_intersect($this->presence->onlineUserIds($today), $user->friendIds())),
            'presencePollSeconds' => (int) config('game.group.presence_poll_seconds'),
        ];
    }
}
