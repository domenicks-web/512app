<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Actions\Notify\BuildNotice;
use App\Actions\Notify\Notifier;
use App\Enums\TradeItemSide;
use App\Enums\TradeStatus;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proposta de troca entre amigos: de 1 a `max_items_per_side` espécimes de
 * cada lado, os oferecidos de quem propõe e os pedidos do amigo, todos
 * revelados. Vence em `expires_after_hours`. A contraproposta passa por
 * aqui também, com `$counterOf`.
 */
class ProposeTrade
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly BuildNotice $notices,
    ) {}

    /**
     * @param  array<int, int>  $offeredIds
     * @param  array<int, int>  $requestedIds
     */
    public function handle(User $proposer, User $friend, array $offeredIds, array $requestedIds, ?string $message, ?Trade $counterOf = null): Trade
    {
        if ($proposer->is($friend) || ! in_array($friend->id, $proposer->friendIds(), true)) {
            $this->fail('trade', 'Só dá pra trocar com amigos.');
        }

        $offered = $this->side($offeredIds, 'offered');
        $requested = $this->side($requestedIds, 'requested');

        if (array_intersect($offered, $requested) !== []) {
            $this->fail('trade', 'Um espécime não pode entrar duas vezes.');
        }

        if (! $this->ownsAll($proposer, $offered)) {
            $this->fail('offered', 'Algum espécime oferecido não é seu.');
        }

        if (! $this->ownsAll($friend, $requested)) {
            $this->fail('requested', "Algum espécime pedido não é de {$friend->nickname}.");
        }

        $max = (int) config('game.trades.max_pending_outgoing');

        if (Trade::query()->open()->where('from_user_id', $proposer->id)->count() >= $max) {
            $this->fail('trade', "Você já tem {$max} propostas esperando resposta.");
        }

        if ($this->alreadyProposed($proposer, $friend, $offered, $requested)) {
            $this->fail('trade', 'Você já propôs essa troca.');
        }

        $message = trim((string) $message);

        return DB::transaction(function () use ($proposer, $friend, $offered, $requested, $message, $counterOf): Trade {
            $trade = Trade::create([
                'from_user_id' => $proposer->id,
                'to_user_id' => $friend->id,
                'counter_of_id' => $counterOf?->id,
                'status' => TradeStatus::Pending,
                'message' => $message === '' ? null : $message,
                'expires_at' => now()->addHours((int) config('game.trades.expires_after_hours')),
            ]);

            $trade->items()->createMany([
                ...array_map(fn (int $id): array => ['specimen_id' => $id, 'side' => TradeItemSide::Offered], $offered),
                ...array_map(fn (int $id): array => ['specimen_id' => $id, 'side' => TradeItemSide::Requested], $requested),
            ]);

            $this->notifier->toPlayer($trade->to_user_id, $this->notices->tradeReceived($trade));

            return $trade;
        });
    }

    /**
     * @param  array<int, int>  $ids
     * @return list<int>
     */
    private function side(array $ids, string $key): array
    {
        $ids = array_map('intval', array_values($ids));
        $max = (int) config('game.trades.max_items_per_side');

        if ($ids === [] || count($ids) > $max) {
            $this->fail($key, "Escolha de 1 a {$max} espécimes de cada lado.");
        }

        if (count(array_unique($ids)) !== count($ids)) {
            $this->fail('trade', 'Um espécime não pode entrar duas vezes.');
        }

        sort($ids);

        return $ids;
    }

    /** @param  list<int>  $ids */
    private function ownsAll(User $owner, array $ids): bool
    {
        // O RevealedScope global já deixa de fora bola fechada.
        return Specimen::query()->whereIn('id', $ids)->where('user_id', $owner->id)->count() === count($ids);
    }

    /**
     * @param  list<int>  $offered
     * @param  list<int>  $requested
     */
    private function alreadyProposed(User $proposer, User $friend, array $offered, array $requested): bool
    {
        return Trade::query()
            ->open()
            ->where('from_user_id', $proposer->id)
            ->where('to_user_id', $friend->id)
            ->with('items')
            ->get()
            ->contains(function (Trade $trade) use ($offered, $requested): bool {
                $ids = fn (TradeItemSide $side): array => $trade->items->where('side', $side)->pluck('specimen_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

                return $ids(TradeItemSide::Offered) === $offered && $ids(TradeItemSide::Requested) === $requested;
            });
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
