<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Models\User;

/**
 * Quem propôs abriu a aba onde o desfecho aparece: recusadas ficam em
 * Concluídas e as que caíram em Enviadas. Aceita não entra aqui (some
 * quando a cerimônia toca, ver `MarkTradeSeen`).
 */
class MarkTradeOutcomesSeen
{
    public function handle(User $user, string $tab): void
    {
        $status = match ($tab) {
            'concluidas' => TradeStatus::Declined,
            'enviadas' => TradeStatus::Cancelled,
            default => null,
        };

        if ($status === null) {
            return;
        }

        Trade::query()
            ->outcomeUnseenBy($user)
            ->where('status', $status)
            ->update(['proposer_seen_at' => now()]);
    }
}
