<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Actions\Profile\ResetUnownedAvatar;
use App\Enums\TradeCancelReason;
use App\Enums\TradeItemSide;
use App\Enums\TradeStatus;
use App\Models\Specimen;
use App\Models\SpecimenTransfer;
use App\Models\Trade;
use App\Models\TradeItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aceite: numa transação, trava a troca e os espécimes (por id, contra
 * deadlock), confere que cada um ainda é de quem era, passa a posse,
 * grava o histórico, derruba as outras propostas com esses espécimes e
 * acerta o avatar dos dois. Se algum espécime mudou de dono, a troca cai
 * com "não disponível" (fica gravado mesmo com o erro).
 */
class AcceptTrade
{
    public function __construct(private readonly ResetUnownedAvatar $resetAvatar) {}

    public function handle(Trade $trade, User $accepter): Trade
    {
        $result = DB::transaction(function () use ($trade, $accepter): Trade|string {
            $locked = Trade::query()->lockForUpdate()->findOrFail($trade->id);

            if ($locked->to_user_id !== $accepter->id || ! $locked->isOpen()) {
                return 'not_pending';
            }

            $items = $locked->items()->get();
            $specimens = Specimen::query()
                ->whereIn('id', $items->pluck('specimen_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                if ($specimens->get($item->specimen_id)?->user_id !== $this->giver($locked, $item)) {
                    $this->cancelUnavailable(Trade::query()->whereKey($locked->id));

                    return 'unavailable';
                }
            }

            foreach ($items as $item) {
                $from = $this->giver($locked, $item);
                $to = $from === $locked->from_user_id ? $locked->to_user_id : $locked->from_user_id;
                $specimens->get($item->specimen_id)?->forceFill(['user_id' => $to])->save();
                SpecimenTransfer::create([
                    'specimen_id' => $item->specimen_id,
                    'from_user_id' => $from,
                    'to_user_id' => $to,
                    'trade_id' => $locked->id,
                    'transferred_at' => now(),
                ]);
            }

            $this->cancelUnavailable(Trade::query()
                ->where('status', TradeStatus::Pending)
                ->whereKeyNot($locked->id)
                ->whereHas('items', fn ($query) => $query->whereIn('specimen_id', $items->pluck('specimen_id'))));

            $locked->update(['status' => TradeStatus::Accepted, 'responded_at' => now()]);
            $this->resetAvatar->handle(User::findOrFail($locked->from_user_id));
            $this->resetAvatar->handle(User::findOrFail($locked->to_user_id));

            return $locked;
        });

        return match ($result) {
            'not_pending' => throw ValidationException::withMessages(['trade' => 'Essa proposta não está mais pendente.']),
            'unavailable' => throw ValidationException::withMessages(['trade' => 'Algum espécime dessa troca não está mais disponível.']),
            default => $result,
        };
    }

    private function giver(Trade $trade, TradeItem $item): int
    {
        return $item->side === TradeItemSide::Offered ? $trade->from_user_id : $trade->to_user_id;
    }

    /** @param  Builder<Trade>  $query */
    private function cancelUnavailable(Builder $query): void
    {
        $query->update([
            'status' => TradeStatus::Cancelled,
            'cancel_reason' => TradeCancelReason::Unavailable,
            'responded_at' => now(),
        ]);
    }
}
