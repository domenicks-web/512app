<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lista do painel de jogadores. A busca casa nickname, "Nick#1234" e email;
 * admins primeiro, depois por ordem de entrada. O filtro "Admins" é no
 * cliente, pra os contadores dos dois filtros saírem da mesma lista.
 */
class SearchPlayers
{
    /**
     * @return Collection<int, User>
     */
    public function handle(?string $search): Collection
    {
        $search = trim((string) $search);

        return User::query()
            ->with(['avatarSpecies:id,sprite_path,sprite_shiny_path', 'invitedBy.createdBy:id,nickname'])
            ->withCount('specimens')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                [$nickname, $tag] = array_pad(explode('#', $search, 2), 2, null);

                $query->where(function (Builder $query) use ($like, $nickname, $tag): void {
                    $query->where('nickname', 'like', $like)
                        ->orWhere('email', 'like', $like);

                    if ($tag !== null) {
                        $query->orWhere(fn (Builder $query) => $query
                            ->where('nickname', 'like', addcslashes((string) $nickname, '%_\\'))
                            ->where('tag', 'like', addcslashes($tag, '%_\\').'%'));
                    }
                });
            })
            ->orderByDesc('is_admin')
            ->orderBy('id')
            ->get();
    }
}
