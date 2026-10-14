<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Enums\TradeCancelReason;
use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quem propôs desiste (vale também pra limpar uma vencida). */
class CancelTrade
{
    public function handle(Trade $trade, User $user): void
    {
        DB::transaction(function () use ($trade, $user): void {
            $locked = Trade::query()->lockForUpdate()->findOrFail($trade->id);

            if ($locked->from_user_id !== $user->id || $locked->status !== TradeStatus::Pending) {
                throw ValidationException::withMessages(['trade' => 'Essa proposta não está mais pendente.']);
            }

            $locked->update([
                'status' => TradeStatus::Cancelled,
                'cancel_reason' => TradeCancelReason::ByProposer,
                'responded_at' => now(),
            ]);
        });
    }
}
