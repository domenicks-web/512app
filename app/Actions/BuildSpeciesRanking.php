<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Specimen;
use App\Models\User;

/**
 * Ranking do grupo por espécies distintas na coleção. Empate em espécies
 * divide a posição (1º, 1º, 3º); shinies só desempatam a ordem da lista.
 * Quem ainda não tem nenhuma espécie fica sem posição (`null`), no fim.
 */
class BuildSpeciesRanking
{
    /**
     * @return list<array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, ownedSpecies: int, shinyCount: int, position: int|null}>
     */
    public function handle(): array
    {
        $users = User::query()
            ->active()
            ->whereNotNull('nickname')
            ->with('avatarSpecies:id,sprite_path,sprite_shiny_path')
            ->select('users.*')
            ->addSelect([
                'owned_species' => Specimen::query()
                    ->selectRaw('count(distinct species_id)')
                    ->whereColumn('user_id', 'users.id'),
                'shiny_count' => Specimen::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('user_id', 'users.id')
                    ->where('is_shiny', true),
            ])
            ->orderByDesc('owned_species')
            ->orderByDesc('shiny_count')
            ->orderBy('id')
            ->get();

        $rows = [];
        $previousOwned = null;
        $position = 0;

        foreach ($users as $index => $user) {
            $owned = (int) $user->getAttribute('owned_species');

            if ($owned !== $previousOwned) {
                $position = $index + 1;
                $previousOwned = $owned;
            }

            $rows[] = [
                'userId' => $user->id,
                'nickname' => (string) $user->nickname,
                'tag' => (string) $user->tag,
                'avatarSpritePath' => $user->avatarSpritePath(),
                'ownedSpecies' => $owned,
                'shinyCount' => (int) $user->getAttribute('shiny_count'),
                'position' => $owned > 0 ? $position : null,
            ];
        }

        return $rows;
    }
}
