<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TradeItemSide;
use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Models\TradeItem;
use App\Models\User;
use Carbon\Carbon;

/**
 * Propostas de troca pendentes do jogador, separadas entre recebidas e
 * enviadas. Só leitura: propor, aceitar e recusar são a fase 4 do SPEC.
 */
class BuildTradeInbox
{
    /**
     * @return array{
     *     incoming: array<int, array<string, mixed>>,
     *     outgoing: array<int, array<string, mixed>>,
     * }
     */
    public function handle(User $user): array
    {
        $trades = Trade::query()
            ->where('status', TradeStatus::Pending)
            ->where(fn ($query) => $query->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id))
            ->with(['fromUser.avatarSpecies:id,sprite_path,sprite_shiny_path', 'toUser.avatarSpecies:id,sprite_path,sprite_shiny_path', 'items.specimen.species:id,name,sprite_path,sprite_shiny_path'])
            ->latest()
            ->latest('id')
            ->get();

        [$incoming, $outgoing] = $trades->partition(fn (Trade $trade): bool => $trade->to_user_id === $user->id);

        return [
            'incoming' => $incoming->map(fn (Trade $trade): array => $this->present($trade, $trade->fromUser))->values()->all(),
            'outgoing' => $outgoing->map(fn (Trade $trade): array => $this->present($trade, $trade->toUser))->values()->all(),
        ];
    }

    /**
     * @return array{id: int, otherTrainer: array{nickname: string, avatarSpritePath: string|null}, message: string|null, createdAgo: string, offered: array<int, array<string, mixed>>, requested: array<int, array<string, mixed>>}
     */
    private function present(Trade $trade, User $otherTrainer): array
    {
        $items = fn (TradeItemSide $side): array => $trade->items
            ->where('side', $side)
            ->map(fn (TradeItem $item): array => [
                'specimenId' => $item->specimen->id,
                'speciesName' => $item->specimen->species->name,
                'spritePath' => $item->specimen->is_shiny ? $item->specimen->species->sprite_shiny_path : $item->specimen->species->sprite_path,
                'isShiny' => $item->specimen->is_shiny,
                'mintNumber' => $item->specimen->mint_number,
            ])
            ->values()
            ->all();

        return [
            'id' => $trade->id,
            'otherTrainer' => [
                'nickname' => (string) $otherTrainer->nickname,
                'avatarSpritePath' => $otherTrainer->avatarSpritePath(),
            ],
            'message' => $trade->message,
            'createdAgo' => $trade->created_at?->diffForHumans(['locale' => 'pt_BR', 'options' => Carbon::JUST_NOW]) ?? '',
            'offered' => $items(TradeItemSide::Offered),
            'requested' => $items(TradeItemSide::Requested),
        ];
    }
}
