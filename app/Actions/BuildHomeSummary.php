<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Species;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Monta os números da Home. Coleção e avatar são dado real; ranking,
 * trocas, feed e amigos online ainda não existem no jogo e vêm de
 * `game.home.placeholders` (provisório, ver CLAUDE.md).
 */
class BuildHomeSummary
{
    /**
     * @return array{
     *     trainer: array{nickname: string, tag: string, avatarSpritePath: string|null, isAdmin: bool},
     *     collection: array{ownedSpecies: int, totalSpecies: int},
     *     packDate: string,
     *     placeholders: array{ranking_position: int, trade_proposals: int, feed: array{is_shiny: bool, headline: string, meta: string}, friends_online: int},
     * }
     */
    public function handle(User $user, CarbonInterface $today): array
    {
        return [
            'trainer' => [
                'nickname' => (string) $user->nickname,
                'tag' => (string) $user->tag,
                'avatarSpritePath' => $user->avatarSpecies?->sprite_path,
                'isAdmin' => $user->is_admin,
            ],
            'collection' => [
                'ownedSpecies' => $user->specimens()->distinct()->count('species_id'),
                'totalSpecies' => Species::count(),
            ],
            'packDate' => $today->locale('pt_BR')->translatedFormat('j M'),
            'placeholders' => config('game.home.placeholders'),
        ];
    }
}
