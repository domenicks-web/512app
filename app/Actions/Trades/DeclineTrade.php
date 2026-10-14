<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** O destinatário recusa uma proposta aberta. */
class DeclineTrade
{
    public function handle(Trade $trade, User $user): void
    {
        DB::transaction(function () use ($trade, $user): void {
            $locked = Trade::query()->lockForUpdate()->findOrFail($trade->id);

            if ($locked->to_user_id !== $user->id || ! $locked->isOpen()) {
                throw ValidationException::withMessages(['trade' => 'Essa proposta não está mais pendente.']);
            }

            $locked->update(['status' => TradeStatus::Declined, 'responded_at' => now()]);
        });
    }
}
