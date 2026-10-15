<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\SanctionType;
use App\Exceptions\SanctionNotAllowedException;
use App\Mail\SanctionedMail;
use App\Models\Friendship;
use App\Models\Sanction;
use App\Models\Specimen;
use App\Models\SpecimenTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Banimento permanente: encerra a suspensão que houver, tira do jogo,
 * apaga amizades e pedidos e liberta todos os espécimes pro Professor
 * Carvalho (mesmo mint, histórico com a libertação). Sem volta pela UI.
 */
class BanPlayer
{
    public function __construct(private readonly EndPlayerAccess $endAccess) {}

    public function handle(?User $admin, User $target, string $reason): Sanction
    {
        $this->endAccess->ensureCanPunish($admin, $target);

        $sanction = DB::transaction(function () use ($admin, $target, $reason): Sanction {
            User::query()->whereKey($target->id)->lockForUpdate()->first();
            $current = $target->activeSanction();

            if ($current?->type === SanctionType::Ban) {
                throw SanctionNotAllowedException::alreadySanctioned();
            }

            $current?->update(['lifted_at' => now(), 'lifted_by' => $admin?->id]);

            $sanction = Sanction::create([
                'user_id' => $target->id,
                'issued_by' => $admin?->id,
                'type' => SanctionType::Ban,
                'reason' => $reason,
            ]);
            $this->endAccess->handle($target);
            Friendship::query()->involving($target)->delete();
            $this->release($target);

            return $sanction;
        });

        Mail::to($target)->send(new SanctionedMail($target, $sanction));

        return $sanction;
    }

    /** Inclusive bola fechada: nada do banido fica pra trás. */
    private function release(User $target): void
    {
        $system = User::system();
        $specimens = Specimen::query()->withoutGlobalScopes()->where('user_id', $target->id)->lockForUpdate()->get(['id', 'nickname']);

        SpecimenTransfer::query()->insert($specimens->map(fn (Specimen $specimen): array => [
            'specimen_id' => $specimen->id,
            'from_user_id' => $target->id,
            'to_user_id' => $system->id,
            'trade_id' => null,
            'nickname' => $specimen->nickname,
            'transferred_at' => now(),
        ])->all());
        Specimen::query()->withoutGlobalScopes()->whereKey($specimens->modelKeys())->update(['user_id' => $system->id, 'nickname' => null]);
    }
}
