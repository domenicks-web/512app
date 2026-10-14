<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RarityTier;
use App\Enums\TradeItemSide;
use App\Enums\TradeStatus;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\TradeItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Os acontecimentos bons mais recentes do grupo: aberturas (todo shiny
 * entra, e além dele os tiers de `game.group.feed_highlight_tiers`; "quem
 * abriu" é o dono da abertura, não o dono atual) e trocas aceitas que
 * moveram shiny ou tier de destaque. Cada item tem `kind` e `key` únicos.
 */
class BuildGroupFeed
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(int $limit): array
    {
        return array_values(collect([...$this->openings($limit), ...$this->trades($limit)])
            ->sortByDesc('at')
            ->take($limit)
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openings(int $limit): array
    {
        $tiers = (array) config('game.group.feed_highlight_tiers');

        return array_values(Specimen::query()
            ->with(['species:id,name,rarity_tier,sprite_path,sprite_shiny_path', 'opening.user:id,nickname,avatar_species_id,avatar_is_shiny', 'opening.user.avatarSpecies:id,sprite_path,sprite_shiny_path'])
            ->where(fn (Builder $query) => $query
                ->where('is_shiny', true)
                ->orWhereHas('species', fn (Builder $species) => $species->whereIn('rarity_tier', $tiers)))
            ->latest('caught_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Specimen $specimen): array => [
                'kind' => 'opening',
                'key' => "opening-{$specimen->id}",
                'at' => $specimen->caught_at->getTimestamp(),
                'id' => $specimen->id,
                'isShiny' => $specimen->is_shiny,
                'trainerId' => $specimen->opening->user->id,
                'trainerNickname' => (string) $specimen->opening->user->nickname,
                'trainerAvatarSpritePath' => $specimen->opening->user->avatarSpritePath(),
                'speciesName' => $specimen->species->name,
                'rarityTier' => $specimen->species->rarity_tier->value,
                'spritePath' => $specimen->is_shiny ? $specimen->species->sprite_shiny_path : $specimen->species->sprite_path,
                'mintNumber' => $specimen->mint_number,
                'caughtAgo' => $specimen->caught_at->diffForHumans(['locale' => 'pt_BR', 'options' => Carbon::JUST_NOW]),
            ])->all());
    }

    /**
     * Trocas aceitas que movem shiny ou tier de destaque: "Lin e Claude
     * trocaram", com o espécime mais vistoso pro sprite.
     *
     * @return list<array<string, mixed>>
     */
    private function trades(int $limit): array
    {
        $tiers = (array) config('game.group.feed_highlight_tiers');

        return array_values(Trade::query()
            ->where('status', TradeStatus::Accepted)
            ->whereHas('items.specimen', fn (Builder $query) => $query
                ->where('is_shiny', true)
                ->orWhereHas('species', fn (Builder $species) => $species->whereIn('rarity_tier', $tiers)))
            ->with(['items.specimen.species', 'fromUser.avatarSpecies', 'toUser.avatarSpecies'])
            ->latest('responded_at')
            ->limit($limit)
            ->get()
            ->map(function (Trade $trade): array {
                $names = fn (TradeItemSide $side): string => $trade->items->where('side', $side)->map(fn (TradeItem $item): string => $item->specimen->species->name)->implode(' + ');
                $star = $trade->items->map(fn (TradeItem $item): Specimen => $item->specimen)
                    ->sortByDesc(fn (Specimen $specimen): int => ($specimen->is_shiny ? 10 : 0) + (int) array_search($specimen->species->rarity_tier, RarityTier::cases(), true))
                    ->first();

                return [
                    'kind' => 'trade',
                    'key' => "trade-{$trade->id}",
                    'tradeId' => $trade->id,
                    'at' => $trade->responded_at?->getTimestamp() ?? 0,
                    'trainerId' => $trade->fromUser->id,
                    'trainerNickname' => (string) $trade->fromUser->nickname,
                    'trainerAvatarSpritePath' => $trade->fromUser->avatarSpritePath(),
                    'partnerNickname' => (string) $trade->toUser->nickname,
                    'summary' => $names(TradeItemSide::Offered).' ⇄ '.$names(TradeItemSide::Requested),
                    'isShiny' => (bool) $star?->is_shiny,
                    'rarityTier' => (string) $star?->species->rarity_tier->value,
                    'spritePath' => (string) ($star?->is_shiny ? $star->species->sprite_shiny_path : $star?->species->sprite_path),
                    'caughtAgo' => $trade->responded_at?->diffForHumans(['locale' => 'pt_BR', 'options' => Carbon::JUST_NOW]) ?? '',
                ];
            })
            ->all());
    }
}
