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
        $ids = Specimen::query()->withoutGlobalScopes()->where('user_id', $target->id)->lockForUpdate()->pluck('id');

        SpecimenTransfer::query()->insert($ids->map(fn (int $id): array => [
            'specimen_id' => $id,
            'from_user_id' => $target->id,
            'to_user_id' => $system->id,
            'trade_id' => null,
            'transferred_at' => now(),
        ])->all());
        Specimen::query()->withoutGlobalScopes()->whereKey($ids)->update(['user_id' => $system->id]);
    }
}
