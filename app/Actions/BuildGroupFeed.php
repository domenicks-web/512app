<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Specimen;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * As aberturas boas mais recentes do grupo: todo shiny entra, e além dele
 * os tiers de `game.group.feed_highlight_tiers`. "Quem abriu" é o dono da
 * abertura, não o dono atual (o espécime pode ter sido trocado depois).
 */
class BuildGroupFeed
{
    /**
     * @return array<int, array{id: int, isShiny: bool, trainerNickname: string, trainerAvatarSpritePath: string|null, speciesName: string, rarityTier: string, spritePath: string, mintNumber: int, caughtAgo: string}>
     */
    public function handle(int $limit): array
    {
        $tiers = (array) config('game.group.feed_highlight_tiers');

        return Specimen::query()
            ->with(['species:id,name,rarity_tier,sprite_path,sprite_shiny_path', 'opening.user:id,nickname,avatar_species_id,avatar_is_shiny', 'opening.user.avatarSpecies:id,sprite_path,sprite_shiny_path'])
            ->where(fn (Builder $query) => $query
                ->where('is_shiny', true)
                ->orWhereHas('species', fn (Builder $species) => $species->whereIn('rarity_tier', $tiers)))
            ->latest('caught_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Specimen $specimen): array => [
                'id' => $specimen->id,
                'isShiny' => $specimen->is_shiny,
                'trainerNickname' => (string) $specimen->opening->user->nickname,
                'trainerAvatarSpritePath' => $specimen->opening->user->avatarSpritePath(),
                'speciesName' => $specimen->species->name,
                'rarityTier' => $specimen->species->rarity_tier->value,
                'spritePath' => $specimen->is_shiny ? $specimen->species->sprite_shiny_path : $specimen->species->sprite_path,
                'mintNumber' => $specimen->mint_number,
                'caughtAgo' => $specimen->caught_at->diffForHumans(['locale' => 'pt_BR', 'options' => Carbon::JUST_NOW]),
            ])
            ->values()
            ->all();
    }
}
