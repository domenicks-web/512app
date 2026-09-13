<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RarityTier;
use App\Models\Species;

class RarityTable
{
    /**
     * Classifica uma espécie em um tier de raridade.
     *
     * Ordem de prioridade: override manual, lendário fixo, depois a regra
     * base por estágio de evolução / soma de status.
     */
    public function classify(Species $species): RarityTier
    {
        $override = config("game.rarity.overrides.{$species->id}");

        if ($override !== null) {
            return RarityTier::from($override);
        }

        if (in_array($species->id, config('game.rarity.legendary_dex_numbers'), true)) {
            return RarityTier::Legendary;
        }

        if ($species->evolution_stage === 3 || $species->base_stat_total >= 490) {
            return RarityTier::Rare;
        }

        if ($species->evolution_stage === 2) {
            return RarityTier::Uncommon;
        }

        return RarityTier::Common;
    }

    /**
     * Pesos por tier configurados em config/game.php.
     *
     * Quando $guaranteedMinRarity é true, o tier comum é removido e os pesos
     * restantes são redistribuídos proporcionalmente (usado no slot que
     * garante incomum ou acima).
     *
     * @return array<string, float> tier->value => peso
     */
    public function weights(bool $guaranteedMinRarity = false): array
    {
        /** @var array<string, float> $weights */
        $weights = config('game.rarity.weights');

        if (! $guaranteedMinRarity) {
            return $weights;
        }

        unset($weights[RarityTier::Common->value]);

        $total = array_sum($weights);

        return array_map(
            fn (float $weight): float => $weight / $total * 100,
            $weights
        );
    }

    /**
     * Escolhe um tier a partir de um roll uniforme em [0, 1).
     */
    public function rollTier(float $roll, bool $guaranteedMinRarity = false): RarityTier
    {
        $weights = $this->weights($guaranteedMinRarity);
        $target = $roll * array_sum($weights);

        $cumulative = 0.0;

        foreach ($weights as $tier => $weight) {
            $cumulative += $weight;

            if ($target < $cumulative) {
                return RarityTier::from($tier);
            }
        }

        // Ponto flutuante pode deixar o roll cair 1 ULP acima do total
        // acumulado; devolve o último tier da lista de pesos nesse caso.
        return RarityTier::from(array_key_last($weights));
    }
}
