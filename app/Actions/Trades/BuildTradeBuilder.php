<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Enums\TradeItemSide;
use App\Http\Resources\TradeSpecimenResource;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * O montador: os espécimes de quem vê e do amigo (um por um, com mint e
 * IV), cada um marcado se o dono tem repetido da espécie, e o que já vem
 * na mesa (o `?pedir=` ou a contraproposta invertida). Os filtros do
 * front só escondem o que já veio daqui.
 */
class BuildTradeBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $viewer, User $friend, Request $request, ?Trade $counterOf = null): array
    {
        $mine = $this->collection($viewer, $request);
        $theirs = $this->collection($friend, $request);
        $theirIds = array_column($theirs, 'specimenId');

        $initial = ['give' => [], 'receive' => []];

        if ($counterOf !== null) {
            $counterOf->loadMissing('items');
            $initial = [
                'give' => $counterOf->items->where('side', TradeItemSide::Requested)->pluck('specimen_id')->map(fn ($id): int => (int) $id)->values()->all(),
                'receive' => $counterOf->items->where('side', TradeItemSide::Offered)->pluck('specimen_id')->map(fn ($id): int => (int) $id)->values()->all(),
            ];
        } elseif (in_array($request->integer('pedir'), $theirIds, true)) {
            $initial['receive'] = [$request->integer('pedir')];
        }

        return [
            'friend' => ['id' => $friend->id, 'nickname' => (string) $friend->nickname, 'tag' => (string) $friend->tag, 'avatarSpritePath' => $friend->avatarSpritePath()],
            'mine' => $mine,
            'theirs' => $theirs,
            'initial' => $initial,
            'counterOf' => $counterOf?->id,
            'limits' => [
                'perSide' => (int) config('game.trades.max_items_per_side'),
                'message' => (int) config('game.trades.message_max_length'),
            ],
        ];
    }

    /**
     * Em ordem de dex; dentro da espécie, shiny e IV maior primeiro.
     *
     * @return list<array<string, mixed>>
     */
    private function collection(User $owner, Request $request): array
    {
        $specimens = $owner->specimens()
            ->with('species')
            ->orderBy('species_id')
            ->orderByDesc('is_shiny')
            ->orderByDesc('iv_total')
            ->get();
        $perSpecies = $specimens->countBy('species_id');

        return array_values($specimens->map(fn (Specimen $specimen): array => [
            ...(new TradeSpecimenResource($specimen))->toArray($request),
            'duplicate' => $perSpecies[$specimen->species_id] > 1,
        ])->all());
    }
}
