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
 * Monta os números da Home: treinador, coleção (com o espécime de maior
 * valor, ou a silhueta de quem não tem nenhum), quando sai o próximo pack,
 * posição e top 3 do ranking de espécies, propostas de troca recebidas e
 * enviadas, destaque do feed e quantos amigos estão online.
 */
class BuildHomeSummary
{
    public function __construct(
        private readonly BuildSpeciesRanking $ranking,
        private readonly FindBestSpecimen $bestSpecimen,
        private readonly FindFeedHighlight $feed,
        private readonly GroupPresence $presence,
    ) {}

    /**
     * @return array{
     *     trainer: array{nickname: string, tag: string, avatarSpritePath: string|null, isAdmin: bool},
     *     collection: array{ownedSpecies: int, totalSpecies: int, shinyCount: int},
     *     bestSpecimen: array{speciesName: string, spritePath: string, isShiny: bool, rarityTier: string, mintNumber: int}|null,
     *     silhouetteSpritePath: string|null,
     *     rankingTop: list<array{nickname: string, avatarSpritePath: string|null, ownedSpecies: int, position: int|null, isViewer: bool}>,
     *     sentTrades: int,
     *     duplicates: array{count: int, spritePaths: array<int, string>},
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
        $ranking = $this->ranking->handle();
        $ownRow = collect($ranking)->firstWhere('userId', $user->id);

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
                'shinyCount' => $user->specimens()->where('is_shiny', true)->count(),
            ],
            'bestSpecimen' => $this->bestSpecimen->handle($user),
            'silhouetteSpritePath' => Species::query()
                ->whereKey(config('game.onboarding.avatar_species_ids')[0])
                ->value('sprite_path'),
            'rankingTop' => array_map(fn (array $row): array => [
                'nickname' => $row['nickname'],
                'avatarSpritePath' => $row['avatarSpritePath'],
                'ownedSpecies' => $row['ownedSpecies'],
                'position' => $row['position'],
                'isViewer' => $row['userId'] === $user->id,
            ], array_slice($ranking, 0, 3)),
            'nextPackAt' => $user->next_pack_at !== null && $user->next_pack_at->isAfter($today)
                ? $user->next_pack_at->toIso8601String()
                : null,
            'rankingPosition' => $ownRow['position'] ?? null,
            'pendingTrades' => Trade::query()
                ->where('to_user_id', $user->id)
                ->where('status', TradeStatus::Pending)
                ->count(),
            'duplicates' => $this->duplicates($user),
            'sentTrades' => Trade::query()
                ->where('from_user_id', $user->id)
                ->where('status', TradeStatus::Pending)
                ->count(),
            'feedHighlight' => $this->feed->handle(),
            'friendsOnline' => count(array_intersect($this->presence->onlineUserIds($today), $user->friendIds())),
            'presencePollSeconds' => (int) config('game.group.presence_poll_seconds'),
        ];
    }

    /**
     * Repetidos: espécimes além do primeiro de cada espécie. Os sprites são
     * das espécies com mais repetidos (até 4), pra ilustrar o card de trocas.
     *
     * @return array{count: int, spritePaths: array<int, string>}
     */
    private function duplicates(User $user): array
    {
        $perSpecies = $user->specimens()
            ->selectRaw('species_id, count(*) as total')
            ->groupBy('species_id')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('total')
            ->orderBy('species_id')
            ->get();

        $spritePaths = Species::query()
            ->whereIn('id', $perSpecies->take(4)->pluck('species_id'))
            ->pluck('sprite_path', 'id');

        return [
            'count' => (int) $perSpecies->sum(fn ($row): int => (int) $row->getAttribute('total') - 1),
            'spritePaths' => $perSpecies->take(4)
                ->map(fn ($row): ?string => $spritePaths[$row->getAttribute('species_id')] ?? null)
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
