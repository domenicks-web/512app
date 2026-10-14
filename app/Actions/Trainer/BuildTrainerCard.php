<?php

declare(strict_types=1);

namespace App\Actions\Trainer;

use App\Actions\BuildCollection;
use App\Actions\BuildSpeciesRanking;
use App\Actions\FindTopSpecimens;
use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;

/**
 * Cartão de Treinador (o Trainer Card dos jogos): quem é o jogador, os
 * números dele, como quem vê está com ele na amizade, o Time (os melhores
 * espécimes) e a Pokédex.
 */
class BuildTrainerCard
{
    public function __construct(
        private readonly BuildSpeciesRanking $ranking,
        private readonly FindTopSpecimens $topSpecimens,
        private readonly BuildCollection $collection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $owner, User $viewer): array
    {
        $rankingRow = collect($this->ranking->handle())->firstWhere('userId', $owner->id);

        return [
            'trainer' => [
                'id' => $owner->id,
                'nickname' => (string) $owner->nickname,
                'tag' => (string) $owner->tag,
                'avatarSpritePath' => $owner->avatarSpritePath(),
                'memberSince' => mb_strtolower((string) $owner->created_at?->setTimezone((string) config('game.display_timezone'))->locale('pt_BR')->translatedFormat('M/Y')),
                'isViewer' => $owner->is($viewer),
            ],
            'stats' => [
                'rankingPosition' => $rankingRow['position'] ?? null,
                'shinyCount' => $owner->specimens()->where('is_shiny', true)->count(),
                'openedPacks' => $owner->openings()->count(),
            ],
            'friendship' => $this->friendship($owner, $viewer),
            'team' => $this->topSpecimens->handle($owner, (int) config('game.profile.team_size')),
            'collection' => $this->collection->handle($owner),
        ];
    }

    /**
     * @return array{state: 'self'|'none'|'outgoing'|'incoming'|'friends', friendshipId: int|null}
     */
    private function friendship(User $owner, User $viewer): array
    {
        if ($owner->is($viewer)) {
            return ['state' => 'self', 'friendshipId' => null];
        }

        $friendship = Friendship::query()->between($owner, $viewer)->first();

        $state = match (true) {
            $friendship === null => 'none',
            $friendship->status === FriendshipStatus::Accepted => 'friends',
            $friendship->requester_id === $viewer->id => 'outgoing',
            default => 'incoming',
        };

        return ['state' => $state, 'friendshipId' => $friendship?->id];
    }
}
