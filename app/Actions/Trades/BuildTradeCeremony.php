<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Enums\TradeItemSide;
use App\Enums\TradeStatus;
use App\Http\Resources\TradeSpecimenResource;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\TradeItem;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * O que a cerimônia mostra pra quem vê: o que recebeu, o que deu e quais
 * espécies recebidas são novas na Pokédex dele (nenhum outro espécime da
 * espécie fora os recebidos nesta troca). `markSeen` diz se o front deve
 * avisar que o proponente viu.
 */
class BuildTradeCeremony
{
    /**
     * @return array{tradeId: int, other: array{id: int, nickname: string, avatarSpritePath: string|null}, received: list<array<string, mixed>>, given: list<array<string, mixed>>, newSpeciesIds: list<int>, markSeen: bool}|null
     */
    public function handle(Trade $trade, User $viewer, Request $request): ?array
    {
        if ($trade->status !== TradeStatus::Accepted || ! in_array($viewer->id, [$trade->from_user_id, $trade->to_user_id], true)) {
            return null;
        }

        $trade->loadMissing(['items.specimen.species', 'fromUser.avatarSpecies', 'toUser.avatarSpecies']);
        $isProposer = $trade->from_user_id === $viewer->id;
        $other = $isProposer ? $trade->toUser : $trade->fromUser;
        $receivedSide = $isProposer ? TradeItemSide::Requested : TradeItemSide::Offered;
        $received = $trade->items->where('side', $receivedSide)->map(fn (TradeItem $item): Specimen => $item->specimen);
        $given = $trade->items->where('side', '!=', $receivedSide)->map(fn (TradeItem $item): Specimen => $item->specimen);
        $card = fn (Specimen $specimen): array => (new TradeSpecimenResource($specimen))->toArray($request);

        $newSpeciesIds = $received
            ->pluck('species_id')
            ->unique()
            ->filter(fn (int $speciesId): bool => ! $viewer->specimens()
                ->where('species_id', $speciesId)
                ->whereNotIn('id', $received->pluck('id'))
                ->exists())
            ->sort()
            ->values()
            ->all();

        return [
            'tradeId' => $trade->id,
            'other' => ['id' => $other->id, 'nickname' => (string) $other->nickname, 'avatarSpritePath' => $other->avatarSpritePath()],
            'received' => $received->map($card)->values()->all(),
            'given' => $given->map($card)->values()->all(),
            'newSpeciesIds' => $newSpeciesIds,
            'markSeen' => $isProposer && $trade->proposer_seen_at === null,
        ];
    }
}
