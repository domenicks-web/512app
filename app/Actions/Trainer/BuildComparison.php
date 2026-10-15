<?php

declare(strict_types=1);

namespace App\Actions\Trainer;

use App\Http\Resources\PackSpecimenResource;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * A tela de comparar: o espécime de quem vê contra outro da mesma espécie,
 * quem ganha cada linha e os outros espécimes de quem vê daquela espécie,
 * pra trocar o lado de cá. Fora dessas regras é 404.
 */
class BuildComparison
{
    public function __construct(private readonly CompareSpecimens $compare = new CompareSpecimens) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $viewer, Specimen $mine, Specimen $theirs, Request $request): array
    {
        if ($mine->user_id !== $viewer->id || $mine->species_id !== $theirs->species_id) {
            throw (new ModelNotFoundException)->setModel(Specimen::class);
        }

        $mine->load('species', 'user');
        $theirs->load('species', 'user');

        // Dono suspenso, banido (o sistema) some do grupo: comparar dá 404.
        if (! $theirs->user->isActive()) {
            throw (new ModelNotFoundException)->setModel(Specimen::class);
        }

        $choices = $viewer->specimens()
            ->where('species_id', $mine->species_id)
            ->orderByDesc('is_shiny')
            ->orderByDesc('iv_total')
            ->orderBy('mint_number')
            ->get(['id', 'is_shiny', 'iv_total', 'mint_number'])
            ->map(fn (Specimen $specimen): array => [
                'id' => $specimen->id,
                'mintNumber' => $specimen->mint_number,
                'isShiny' => $specimen->is_shiny,
                'ivTotal' => (int) $specimen->iv_total,
                'isCurrent' => $specimen->id === $mine->id,
            ])
            ->values()
            ->all();

        return [
            'mine' => (new PackSpecimenResource($mine))->toArray($request),
            'theirs' => (new PackSpecimenResource($theirs))->toArray($request),
            'mineOwner' => ['nickname' => (string) $mine->user->nickname, 'tag' => (string) $mine->user->tag],
            'theirsOwner' => ['id' => $theirs->user->id, 'nickname' => (string) $theirs->user->nickname, 'tag' => (string) $theirs->user->tag],
            'comparison' => $this->compare->handle($mine, $theirs),
            'choices' => $choices,
        ];
    }
}
