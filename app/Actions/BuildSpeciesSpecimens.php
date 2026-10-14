<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Resources\PackSpecimenResource;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\SpecimenTransfer;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Os espécimes que o jogador tem de uma espécie, pra ficha da coleção:
 * shiny primeiro, depois o de IV maior. Cada um vem com o histórico de
 * donos: a captura (dono da abertura) e cada troca, em ordem.
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
            ->with(['species', 'opening.user:id,nickname', 'transfers.fromUser:id,nickname', 'transfers.toUser:id,nickname'])
            ->orderByDesc('is_shiny')
            ->orderByDesc('iv_total')
            ->orderBy('mint_number')
            ->get()
            ->map(fn (Specimen $specimen): array => [
                ...(new PackSpecimenResource($specimen))->toArray($request),
                'history' => [
                    [
                        'kind' => 'caught',
                        'trainerId' => $specimen->opening->user->id,
                        'nickname' => (string) $specimen->opening->user->nickname,
                        'fromNickname' => null,
                        'at' => $specimen->caught_at->toIso8601String(),
                    ],
                    ...$specimen->transfers->map(fn (SpecimenTransfer $transfer): array => [
                        'kind' => 'traded',
                        'trainerId' => $transfer->toUser->id,
                        'nickname' => (string) $transfer->toUser->nickname,
                        'fromNickname' => (string) $transfer->fromUser->nickname,
                        'at' => $transfer->transferred_at->toIso8601String(),
                    ])->all(),
                ],
            ])
            ->all());
    }
}

