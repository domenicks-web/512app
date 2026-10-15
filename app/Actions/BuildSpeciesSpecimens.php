<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Os espécimes que o jogador tem de uma espécie, pra ficha da coleção:
 * shiny primeiro, depois o de IV maior, cada um com o histórico de donos.
 */
class BuildSpeciesSpecimens
{
    public function __construct(private readonly PresentSpecimenSheet $present) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(User $user, Species $species, Request $request): array
    {
        return array_values($user->specimens()
            ->where('species_id', $species->id)
            ->with(PresentSpecimenSheet::RELATIONS)
            ->orderByDesc('is_shiny')
            ->orderByDesc('iv_total')
            ->orderBy('mint_number')
            ->get()
            ->map(fn (Specimen $specimen): array => $this->present->handle($specimen, $request))
            ->all());
    }
}
