<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Notify\BuildNotice;
use App\Actions\Notify\Notifier;
use App\Enums\TradeCancelReason;
use App\Enums\TradeStatus;
use App\Exceptions\SanctionNotAllowedException;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * O que toda sanção faz com o acesso: derruba as sessões, invalida o
 * "manter conectado" e cancela as trocas abertas dele (quem tinha
 * proposto pra ele fica sabendo que caiu). Também guarda as regras de
 * quem pode ser punido.
 */
class EndPlayerAccess
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly BuildNotice $notices,
    ) {}

    public function ensureCanPunish(?User $admin, User $target): void
    {
        if ($admin?->is($target)) {
            throw SanctionNotAllowedException::self();
        }

        if ($target->is_system) {
            throw SanctionNotAllowedException::system();
        }

        if ($target->is_admin) {
            throw SanctionNotAllowedException::admin();
        }
    }

    public function handle(User $target): void
    {
        DB::table('sessions')->where('user_id', $target->id)->delete();
        $target->forceFill(['remember_token' => Str::random(60)])->save();

        $open = Trade::query()->open()->involving($target)->with('toUser.avatarSpecies')->get();
        Trade::query()->whereKey($open->modelKeys())->update([
            'status' => TradeStatus::Cancelled,
            'cancel_reason' => TradeCancelReason::Unavailable,
            'responded_at' => now(),
        ]);

        // A proposta que ele mandou só some da caixa do outro; quem tinha
        // proposto pra ele recebe o aviso de que caiu.
        foreach ($open->where('to_user_id', $target->id) as $trade) {
            $this->notifier->toPlayer($trade->from_user_id, $this->notices->tradeFallen($trade));
        }
    }
}
