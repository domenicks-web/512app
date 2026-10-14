<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Models\User;

/** Quem propôs já viu a cerimônia da troca aceita: não toca de novo sozinha. */
class MarkTradeSeen
{
    public function handle(Trade $trade, User $user): void
    {
        Trade::query()
            ->whereKey($trade->id)
            ->where('from_user_id', $user->id)
            ->where('status', TradeStatus::Accepted)
            ->whereNull('proposer_seen_at')
            ->update(['proposer_seen_at' => now()]);
    }
}
