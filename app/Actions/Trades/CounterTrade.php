<?php

declare(strict_types=1);

namespace App\Actions\Trades;

use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contraproposta: recusa a original e cria uma nova na direção contrária,
 * ligada por `counter_of_id`. Tudo numa transação: se a nova não passar
 * nas regras do `ProposeTrade`, a original continua pendente.
 */
class CounterTrade
{
    public function __construct(private readonly ProposeTrade $propose) {}

    /**
     * @param  array<int, int>  $offeredIds
     * @param  array<int, int>  $requestedIds
     */
    public function handle(Trade $original, User $user, array $offeredIds, array $requestedIds, ?string $message): Trade
    {
        return DB::transaction(function () use ($original, $user, $offeredIds, $requestedIds, $message): Trade {
            $locked = Trade::query()->lockForUpdate()->findOrFail($original->id);

            if ($locked->to_user_id !== $user->id || ! $locked->isOpen()) {
                throw ValidationException::withMessages(['trade' => 'Essa proposta não está mais pendente.']);
            }

            $locked->update(['status' => TradeStatus::Declined, 'responded_at' => now()]);

            return $this->propose->handle($user, User::findOrFail($locked->from_user_id), $offeredIds, $requestedIds, $message, $locked);
        });
    }
}
