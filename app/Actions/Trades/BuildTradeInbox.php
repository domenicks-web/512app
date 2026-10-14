<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Enums\TradeItemSide;
use App\Enums\TradeStatus;
use App\Http\Resources\TradeSpecimenResource;
use App\Models\Trade;
use App\Models\TradeItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * A tela de trocas: recebidas abertas, enviadas (abertas e as encerradas
 * sem resposta: vencidas e canceladas) e concluídas (aceitas e recusadas),
 * as encerradas só por `closed_visible_days`. Cada carta vem do ponto de
 * vista de quem vê (`give` / `receive`). Também diz qual cerimônia tocar:
 * a pedida em `?troca=` ou a primeira aceita que o proponente não viu.
 */
class BuildTradeInbox
{
    public function __construct(private readonly BuildTradeCeremony $ceremony) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $viewer, Request $request): array
    {
        $since = now()->subDays((int) config('game.trades.closed_visible_days'));
        $trades = Trade::query()
            ->involving($viewer)
            ->where(fn ($query) => $query->where('status', TradeStatus::Pending)->orWhere('responded_at', '>=', $since))
            ->with(['items.specimen.species', 'fromUser.avatarSpecies', 'toUser.avatarSpecies'])
            ->latest()
            ->latest('id')
            ->get()
            ->reject(fn (Trade $trade): bool => $trade->state() === 'expired' && $trade->expires_at->lt($since));

        $present = fn (Trade $trade): array => $this->present($trade, $viewer, $request);
        $tab = in_array($request->query('aba'), ['enviadas', 'concluidas'], true) ? (string) $request->query('aba') : 'recebidas';

        return [
            'tab' => $tab,
            'received' => $trades->filter(fn (Trade $t): bool => $t->to_user_id === $viewer->id && $t->state() === 'open')->map($present)->values()->all(),
            'sent' => $trades->filter(fn (Trade $t): bool => $t->from_user_id === $viewer->id && in_array($t->state(), ['open', 'expired', 'cancelled'], true))->map($present)->values()->all(),
            'done' => $trades->filter(fn (Trade $t): bool => in_array($t->state(), ['accepted', 'declined'], true))->map($present)->values()->all(),
            'friends' => User::query()->whereIn('id', $viewer->friendIds())->with('avatarSpecies')->orderBy('nickname')->get()
                ->map(fn (User $friend): array => ['id' => $friend->id, 'nickname' => (string) $friend->nickname, 'avatarSpritePath' => $friend->avatarSpritePath()])
                ->all(),
            'ceremony' => $this->ceremonyFor($viewer, $request),
            'expiresAfterHours' => (int) config('game.trades.expires_after_hours'),
            'presencePollSeconds' => (int) config('game.group.presence_poll_seconds'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ceremonyFor(User $viewer, Request $request): ?array
    {
        $trade = $request->filled('troca')
            ? Trade::query()->find($request->integer('troca'))
            : Trade::query()->where('from_user_id', $viewer->id)->where('status', TradeStatus::Accepted)->whereNull('proposer_seen_at')->oldest('responded_at')->first();

        $ceremony = $trade === null ? null : $this->ceremony->handle($trade, $viewer, $request);

        // Reaberta por "Concluídas": toca sem marcar nada.
        if ($ceremony !== null && $request->filled('troca')) {
            $ceremony['markSeen'] = false;
        }

        return $ceremony;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Trade $trade, User $viewer, Request $request): array
    {
        $isProposer = $trade->from_user_id === $viewer->id;
        $other = $isProposer ? $trade->toUser : $trade->fromUser;
        $side = fn (TradeItemSide $side): array => $trade->items->where('side', $side)
            ->map(fn (TradeItem $item): array => (new TradeSpecimenResource($item->specimen))->toArray($request))
            ->values()
            ->all();

        return [
            'id' => $trade->id,
            'direction' => $isProposer ? 'sent' : 'received',
            'state' => $trade->state(),
            'cancelReason' => $trade->cancel_reason?->value,
            'other' => ['id' => $other->id, 'nickname' => (string) $other->nickname, 'avatarSpritePath' => $other->avatarSpritePath()],
            'message' => $trade->message,
            'isCounter' => $trade->counter_of_id !== null,
            'expiresAt' => $trade->expires_at->toIso8601String(),
            'createdAgo' => $trade->created_at->diffForHumans(['locale' => 'pt_BR', 'options' => Carbon::JUST_NOW]),
            'give' => $side($isProposer ? TradeItemSide::Offered : TradeItemSide::Requested),
            'receive' => $side($isProposer ? TradeItemSide::Requested : TradeItemSide::Offered),
        ];
    }
}
