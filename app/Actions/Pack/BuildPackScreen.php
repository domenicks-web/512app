<?php

declare(strict_types=1);

namespace App\Actions\Pack;

use App\Http\Resources\PackSpecimenResource;
use App\Models\Opening;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Estado da tela do pack. Três casos:
 * - pack disponível: idle, com o hash da seed já comprometida;
 * - pack aberto com bola fechada: as bolas, abertas como sprite;
 * - tudo aberto e ainda em cooldown: o resumo e o comprovante.
 * Bola fechada nunca manda o conteúdo.
 */
class BuildPackScreen
{
    public function __construct(private readonly CommitServerSeed $commitServerSeed = new CommitServerSeed) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Request $request): array
    {
        $this->commitServerSeed->handle($user);

        $latest = Opening::where('user_id', $user->id)->latest('id')->with('specimens.species')->first();
        $onCooldown = $user->next_pack_at !== null && $user->next_pack_at->isFuture();
        $current = $latest !== null && (! $latest->isComplete() || $onCooldown) ? $latest : null;

        return [
            'serverSeedHash' => $user->next_server_seed_hash,
            'clientSeed' => $user->client_seed,
            'clientSeedMaxLength' => (int) config('game.pack.client_seed_max_length'),
            'nextPackAt' => $onCooldown ? $user->next_pack_at->toIso8601String() : null,
            'guaranteedSlot' => (int) config('game.pack.guaranteed_slot'),
            'opening' => $current !== null ? $this->opening($current, $request) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function opening(Opening $opening, Request $request): array
    {
        $opening->loadMissing('specimens.species');
        $isComplete = $opening->isComplete();

        return [
            'id' => $opening->id,
            'createdAt' => $opening->created_at->toIso8601String(),
            'isComplete' => $isComplete,
            'slots' => $opening->specimens->sortBy('slot')->map(fn (Specimen $specimen): array => [
                'slot' => $specimen->slot,
                'specimen' => $opening->isSlotOpened($specimen->slot)
                    ? (new PackSpecimenResource($specimen))->toArray($request)
                    : null,
            ])->values()->all(),
            'receipt' => $isComplete ? [
                'serverSeed' => $opening->server_seed,
                'serverSeedHash' => $opening->server_seed_hash,
                'clientSeed' => $opening->client_seed,
                'nonce' => $opening->nonce,
            ] : null,
        ];
    }
}
