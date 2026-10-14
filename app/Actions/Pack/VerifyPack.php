<?php

declare(strict_types=1);

namespace App\Actions\Pack;

use App\Actions\DrawPack;
use App\Actions\ResolveSpecimen;
use App\Enums\Nature;
use App\Models\Species;
use App\Services\ProvablyFair;

/**
 * Refaz a conta de um pack a partir das seeds (SPEC, seção 6): o mesmo
 * DrawPack e o mesmo ResolveSpecimen da abertura, sem gravar nada. O mint
 * não sai da seed (é a ordem de chegada), então não aparece aqui.
 */
class VerifyPack
{
    public function __construct(
        private readonly DrawPack $drawPack = new DrawPack,
        private readonly ResolveSpecimen $resolveSpecimen = new ResolveSpecimen,
        private readonly ProvablyFair $provablyFair = new ProvablyFair,
        private readonly ListSpeciesIdsByTier $listSpeciesIdsByTier = new ListSpeciesIdsByTier,
    ) {}

    /**
     * @return array{computedHash: string, hashMatches: bool|null, slots: list<array<string, mixed>>}
     */
    public function handle(string $serverSeed, string $clientSeed, int $nonce, ?string $serverSeedHash): array
    {
        $draws = $this->drawPack->handle($serverSeed, $clientSeed, $nonce, $this->listSpeciesIdsByTier->handle());
        $species = Species::whereKey(array_map(fn ($draw): int => $draw->speciesId, $draws))->get()->keyBy('id');
        $slots = [];

        foreach ($draws as $draw) {
            /** @var Species $drawnSpecies */
            $drawnSpecies = $species[$draw->speciesId];
            $data = $this->resolveSpecimen->handle($draw->seed, $drawnSpecies, 0);
            $ivs = [$data->ivHp, $data->ivAtk, $data->ivDef, $data->ivSpa, $data->ivSpd, $data->ivSpe];

            $slots[] = [
                'slot' => $draw->slot,
                'seed' => $draw->seed,
                'speciesId' => $drawnSpecies->id,
                'dex' => str_pad((string) $drawnSpecies->id, 3, '0', STR_PAD_LEFT),
                'name' => $drawnSpecies->name,
                'tier' => $draw->tier->value,
                'isShiny' => $data->isShiny,
                'sprite' => '/storage/'.($data->isShiny ? $drawnSpecies->sprite_shiny_path : $drawnSpecies->sprite_path),
                'sizeClass' => $data->sizeClass->value,
                'nature' => ucfirst($data->nature->value),
                'natureIndex' => (int) array_search($data->nature, Nature::cases(), true),
                'ivs' => $ivs,
                'ivTotal' => $data->ivTotal(),
            ];
        }

        return [
            'computedHash' => $this->provablyFair->hash($serverSeed),
            'hashMatches' => $serverSeedHash === null ? null : $this->provablyFair->verify($serverSeed, strtolower($serverSeedHash)),
            'slots' => $slots,
        ];
    }
}
