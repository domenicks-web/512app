<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Resources\PackSpecimenResource;
use App\Models\Specimen;
use App\Models\SpecimenTransfer;
use Illuminate\Http\Request;

/**
 * Um espécime como a ficha mostra: os dados da abertura e o histórico de
 * donos (a captura, pelo dono da abertura, cada troca e a libertação do
 * banimento, em ordem). Espera
 * `species`, `opening.user` e `transfers.fromUser|toUser` já carregados.
 */
class PresentSpecimenSheet
{
    /** Relações que a ficha usa, pro `with()` de quem chama. */
    public const RELATIONS = ['species', 'opening.user:id,nickname', 'transfers.fromUser:id,nickname', 'transfers.toUser:id,nickname'];

    /**
     * @return array<string, mixed>
     */
    public function handle(Specimen $specimen, Request $request): array
    {
        $transfers = $specimen->transfers->values();
        // Apelido que cada dono deu, na ordem: dono 0 (captura) até o atual.
        $nicknames = [...$transfers->pluck('nickname')->all(), $specimen->nickname];

        return [
            ...(new PackSpecimenResource($specimen))->toArray($request),
            'canName' => $request->user()?->id === $specimen->user_id && $specimen->nickname === null,
            'history' => [
                [
                    'kind' => 'caught',
                    'trainerId' => $specimen->opening->user->id,
                    'nickname' => (string) $specimen->opening->user->nickname,
                    'fromNickname' => null,
                    'specimenNickname' => $nicknames[0],
                    'at' => $specimen->caught_at->toIso8601String(),
                ],
                ...$transfers->map(fn (SpecimenTransfer $transfer, int $index): array => [
                    'kind' => $transfer->trade_id === null ? 'released' : 'traded',
                    'trainerId' => $transfer->toUser->id,
                    'nickname' => (string) $transfer->toUser->nickname,
                    'fromNickname' => (string) $transfer->fromUser->nickname,
                    'specimenNickname' => $nicknames[$index + 1],
                    'at' => $transfer->transferred_at->toIso8601String(),
                ])->all(),
            ],
        ];
    }
}
