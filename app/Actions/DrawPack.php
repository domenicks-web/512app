<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Data\DrawnSlot;
use App\Services\ProvablyFair;
use App\Services\RarityTable;
use LogicException;

/**
 * Sorteia os slots de um pack. Função pura, como o ResolveSpecimen: entra
 * server seed, client seed, nonce e a lista de espécies por tier; sai
 * sempre o mesmo resultado. Bytes 0–3 de cada seed escolhem o tier (o
 * último slot nunca é comum) e, com o resto da faixa, a espécie dentro
 * do tier, em ordem de dex.
 */
class DrawPack
{
    public function __construct(
        private readonly ProvablyFair $provablyFair = new ProvablyFair,
        private readonly RarityTable $rarityTable = new RarityTable,
    ) {}

    /**
     * @param  array<string, list<int>>  $speciesIdsByTier  tier->value => ids em ordem de dex
     * @return list<DrawnSlot>
     */
    public function handle(string $serverSeed, string $clientSeed, int $nonce, array $speciesIdsByTier): array
    {
        $slots = (int) config('game.pack.slots');
        $guaranteedSlot = (int) config('game.pack.guaranteed_slot');
        $drawn = [];

        for ($slot = 1; $slot <= $slots; $slot++) {
            $seed = $this->provablyFair->slotSeed($serverSeed, $clientSeed, $nonce, $slot);
            $roll = $this->uint32($seed, 0) / 4294967296;

            [$tier, $residual] = $this->rarityTable->rollTierWithResidual($roll, $slot === $guaranteedSlot);
            $candidates = $speciesIdsByTier[$tier->value] ?? [];

            if ($candidates === []) {
                throw new LogicException("Nenhuma espécie no tier {$tier->value}. Rode o pokedex:sync.");
            }

            $index = min(count($candidates) - 1, (int) floor($residual * count($candidates)));
            $drawn[] = new DrawnSlot($slot, $seed, $tier, $candidates[$index]);
        }

        return $drawn;
    }

    private function uint32(string $hexSeed, int $byteOffset): int
    {
        return (int) hexdec(substr($hexSeed, $byteOffset * 2, 8));
    }
}
