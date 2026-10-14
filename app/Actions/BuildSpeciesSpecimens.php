<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Resources\PackSpecimenResource;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Os espécimes que o jogador tem de uma espécie, pra ficha da coleção:
 * shiny primeiro, depois o de IV maior.
 */
class BuildSpeciesSpecimens
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(User $user, Species $species, Request $request): array
    {
        return array_values($user->specimens()
            ->where('species_id', $species->id)
            ->with('species')
            ->orderByDesc('is_shiny')
            ->orderByDesc('iv_total')
            ->orderBy('mint_number')
            ->get()
            ->map(fn (Specimen $specimen): array => (new PackSpecimenResource($specimen))->toArray($request))
            ->all());
    }
}
