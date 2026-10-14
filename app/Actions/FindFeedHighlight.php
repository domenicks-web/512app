<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Specimen;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * A abertura boa mais recente do grupo: todo shiny entra, e além dele os
 * tiers de `game.group.feed_highlight_tiers`. "Quem abriu" é o dono da
 * abertura, não o dono atual (o espécime pode ter sido trocado depois).
 */
class FindFeedHighlight
{
    /**
     * @return array{isShiny: bool, trainerNickname: string, speciesName: string, mintNumber: int, caughtAgo: string}|null
     */
    public function handle(): ?array
    {
        $tiers = (array) config('game.group.feed_highlight_tiers');

        $specimen = Specimen::query()
            ->with(['species:id,name', 'opening.user:id,nickname'])
            ->where(fn (Builder $query) => $query
                ->where('is_shiny', true)
                ->orWhereHas('species', fn (Builder $species) => $species->whereIn('rarity_tier', $tiers)))
            ->latest('caught_at')
            ->latest('id')
            ->first();

        if ($specimen === null) {
            return null;
        }

        return [
            'isShiny' => $specimen->is_shiny,
            'trainerNickname' => (string) $specimen->opening->user->nickname,
            'speciesName' => $specimen->species->name,
            'mintNumber' => $specimen->mint_number,
            'caughtAgo' => $specimen->caught_at->locale('pt_BR')->diffForHumans(['options' => Carbon::JUST_NOW]),
        ];
    }
}
