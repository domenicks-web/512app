<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\SanctionType;
use App\Exceptions\SanctionNotAllowedException;
use App\Mail\SanctionedMail;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Suspende por um dos prazos do config: tira do jogo e do grupo até
 * vencer, sem mexer nos espécimes. Avisa por email com o motivo.
 */
class SuspendPlayer
{
    public function __construct(private readonly EndPlayerAccess $endAccess) {}

    public function handle(?User $admin, User $target, int $days, string $reason): Sanction
    {
        $this->endAccess->ensureCanPunish($admin, $target);

        if (! in_array($days, (array) config('game.sanctions.suspension_days'), true)) {
            throw SanctionNotAllowedException::invalidDays();
        }

        $sanction = DB::transaction(function () use ($admin, $target, $days, $reason): Sanction {
            User::query()->whereKey($target->id)->lockForUpdate()->first();

            if ($target->activeSanction() !== null) {
                throw SanctionNotAllowedException::alreadySanctioned();
            }

            $sanction = Sanction::create([
                'user_id' => $target->id,
                'issued_by' => $admin?->id,
                'type' => SanctionType::Suspension,
                'reason' => $reason,
                'ends_at' => now()->addDays($days),
            ]);
            $this->endAccess->handle($target);

            return $sanction;
        });

        Mail::to($target)->send(new SanctionedMail($target, $sanction));

        return $sanction;
    }
}
