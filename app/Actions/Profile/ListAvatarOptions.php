<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\Species;
use App\Models\User;

/**
 * Parceiros que o jogador pode usar de avatar: os fixos do config (na ordem
 * curada, sempre na versão normal) e os da coleção, em ordem de dex. Da
 * coleção entra a versão normal de quem ele tem um espécime normal e a
 * shiny de quem ele tem um shiny; normal repetido dos fixos não volta.
 * Espécime de bola ainda fechada não conta (`RevealedScope`).
 */
class ListAvatarOptions
{
    /**
     * @return array{
     *     partners: array<int, array{id: int, name: string, sprite_path: string, is_shiny: bool}>,
     *     collection: list<array{id: int, name: string, sprite_path: string, is_shiny: bool}>,
     * }
     */
    public function handle(User $user): array
    {
        /** @var list<int> $partnerIds */
        $partnerIds = config('game.onboarding.avatar_species_ids');

        $partners = Species::query()
            ->whereIn('id', $partnerIds)
            ->get(['id', 'name', 'sprite_path'])
            ->sortBy(fn (Species $species): int => (int) array_search($species->id, $partnerIds, true))
            ->map(fn (Species $species): array => [
                'id' => $species->id,
                'name' => $species->name,
                'sprite_path' => $species->sprite_path,
                'is_shiny' => false,
            ])
            ->values()
            ->all();

        $owned = $user->specimens()
            ->select(['species_id', 'is_shiny'])
            ->distinct()
            ->get()
            ->groupBy('species_id');

        $collection = [];

        foreach (Species::query()->whereIn('id', $owned->keys())->orderBy('id')->get(['id', 'name', 'sprite_path', 'sprite_shiny_path']) as $species) {
            $variants = $owned->get($species->id)?->pluck('is_shiny')->map(fn (mixed $isShiny): bool => (bool) $isShiny);

            if ($variants?->contains(false) && ! in_array($species->id, $partnerIds, true)) {
                $collection[] = ['id' => $species->id, 'name' => $species->name, 'sprite_path' => $species->sprite_path, 'is_shiny' => false];
            }

            if ($variants?->contains(true)) {
                $collection[] = ['id' => $species->id, 'name' => $species->name, 'sprite_path' => $species->sprite_shiny_path, 'is_shiny' => true];
            }
        }

        return ['partners' => $partners, 'collection' => $collection];
    }

    /** Se o par espécie + shiny está entre as opções do jogador. */
    public function allows(User $user, int $speciesId, bool $isShiny): bool
    {
        if (! $isShiny && in_array($speciesId, (array) config('game.onboarding.avatar_species_ids'), true)) {
            return true;
        }

        return $user->specimens()
            ->where('species_id', $speciesId)
            ->where('is_shiny', $isShiny)
            ->exists();
    }
}
