<?php

declare(strict_types=1);

namespace App\Actions\Pack;

use App\Actions\Notify\BuildNotice;
use App\Actions\Notify\Notifier;
use App\Models\Opening;
use App\Models\Specimen;
use Illuminate\Support\Facades\DB;

/**
 * Abre uma bola do pack: marca o slot, solta o espécime pro resto do jogo
 * e, quando a última bola abre, revela a server seed. Abrir de novo é
 * no-op (devolve o mesmo espécime). Shiny ou tier do feed avisa o grupo.
 */
class RevealSlot
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly BuildNotice $notices,
    ) {}

    public function handle(Opening $opening, int $slot): Specimen
    {
        return DB::transaction(function () use ($opening, $slot): Specimen {
            /** @var Opening $locked */
            $locked = Opening::whereKey($opening->id)->lockForUpdate()->firstOrFail();

            /** @var Specimen $specimen */
            $specimen = $locked->specimens()->where('slot', $slot)->firstOrFail();

            if ($specimen->revealed_at === null) {
                $specimen->forceFill(['revealed_at' => now()])->save();
                $specimen->loadMissing('species', 'opening.user');

                if ($specimen->is_shiny || in_array($specimen->species->rarity_tier->value, (array) config('game.group.feed_highlight_tiers'), true)) {
                    $this->notifier->toGroup($this->notices->feedHighlight($specimen));
                }
            }

            $locked->forceFill(['opened_slots' => $locked->opened_slots | (1 << ($slot - 1))]);

            if ($locked->isComplete() && $locked->revealed_at === null) {
                $locked->forceFill(['revealed_at' => now()]);
            }

            $locked->save();
            $opening->setRawAttributes($locked->getAttributes(), true);

            return $specimen;
        });
    }
}
