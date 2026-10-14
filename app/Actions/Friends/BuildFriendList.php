<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\Specimen;
use App\Models\User;
use App\Services\GroupPresence;
use Carbon\CarbonInterface;

/**
 * A tela Amigos: amigos (online primeiro, depois por nickname) com
 * quantas espécies cada um tem, e os pedidos pendentes recebidos e
 * enviados.
 */
class BuildFriendList
{
    public function __construct(private readonly GroupPresence $presence) {}

    /**
     * @return array{
     *     friends: list<array{friendshipId: int, userId: int, nickname: string, tag: string, avatarSpritePath: string|null, ownedSpecies: int, isOnline: bool}>,
     *     incoming: list<array{friendshipId: int, nickname: string, tag: string, avatarSpritePath: string|null, sentAgo: string}>,
     *     outgoing: list<array{friendshipId: int, nickname: string, tag: string, avatarSpritePath: string|null, sentAgo: string}>,
     * }
     */
    public function handle(User $viewer, CarbonInterface $now): array
    {
        $friendships = Friendship::query()
            ->involving($viewer)
            ->with(['requester.avatarSpecies:id,sprite_path', 'addressee.avatarSpecies:id,sprite_path'])
            ->latest()
            ->latest('id')
            ->get();

        [$accepted, $pending] = $friendships->partition(fn (Friendship $friendship): bool => $friendship->status === FriendshipStatus::Accepted);
        $online = $this->presence->onlineUserIds($now);
        $ownedSpecies = Specimen::query()
            ->whereIn('user_id', $accepted->map(fn (Friendship $friendship): int => $friendship->otherThan($viewer)->id))
            ->selectRaw('user_id, count(distinct species_id) as owned_species')
            ->groupBy('user_id')
            ->pluck('owned_species', 'user_id');

        $friends = $accepted
            ->map(function (Friendship $friendship) use ($viewer, $online, $ownedSpecies): array {
                $friend = $friendship->otherThan($viewer);

                return [
                    'friendshipId' => $friendship->id,
                    'userId' => $friend->id,
                    'nickname' => (string) $friend->nickname,
                    'tag' => (string) $friend->tag,
                    'avatarSpritePath' => $friend->avatarSpecies?->sprite_path,
                    'ownedSpecies' => (int) ($ownedSpecies[$friend->id] ?? 0),
                    'isOnline' => in_array($friend->id, $online, true),
                ];
            })
            ->sort(fn (array $a, array $b): int => [$b['isOnline'], mb_strtolower($a['nickname'])]
                <=> [$a['isOnline'], mb_strtolower($b['nickname'])])
            ->values()
            ->all();

        $present = fn (Friendship $friendship): array => [
            'friendshipId' => $friendship->id,
            'nickname' => (string) $friendship->otherThan($viewer)->nickname,
            'tag' => (string) $friendship->otherThan($viewer)->tag,
            'avatarSpritePath' => $friendship->otherThan($viewer)->avatarSpecies?->sprite_path,
            'sentAgo' => $friendship->created_at?->locale('pt_BR')->diffForHumans() ?? '',
        ];

        return [
            'friends' => $friends,
            'incoming' => $pending->where('addressee_id', $viewer->id)->map($present)->values()->all(),
            'outgoing' => $pending->where('requester_id', $viewer->id)->map($present)->values()->all(),
        ];
    }
}
