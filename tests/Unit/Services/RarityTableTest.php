<?php

declare(strict_types=1);

use App\Enums\RarityTier;
use App\Models\Species;
use App\Services\RarityTable;

it('classifica os 5 lendários fixos independente dos atributos', function () {
    $table = new RarityTable;
    $mewtwo = Species::factory()->make(['id' => 150, 'evolution_stage' => 1, 'base_stat_total' => 680]);

    expect($table->classify($mewtwo))->toBe(RarityTier::Legendary);
});

it('classifica raro por estágio de evolução 3', function () {
    $table = new RarityTable;
    $species = Species::factory()->make(['id' => 6, 'evolution_stage' => 3, 'base_stat_total' => 534]);

    expect($table->classify($species))->toBe(RarityTier::Rare);
});

it('classifica raro por base_stat_total alto mesmo em estágio 1', function () {
    $table = new RarityTable;
    $species = Species::factory()->make(['id' => 999, 'evolution_stage' => 1, 'base_stat_total' => 500]);

    expect($table->classify($species))->toBe(RarityTier::Rare);
});

it('classifica incomum por estágio de evolução 2', function () {
    $table = new RarityTable;
    $species = Species::factory()->make(['id' => 998, 'evolution_stage' => 2, 'base_stat_total' => 300]);

    expect($table->classify($species))->toBe(RarityTier::Uncommon);
});

it('classifica comum o resto', function () {
    $table = new RarityTable;
    $species = Species::factory()->make(['id' => 997, 'evolution_stage' => 1, 'base_stat_total' => 250]);

    expect($table->classify($species))->toBe(RarityTier::Common);
});

it('respeita os overrides manuais mesmo quando a regra base diria outra coisa', function () {
    $table = new RarityTable;
    $ditto = Species::factory()->make(['id' => 132, 'evolution_stage' => 1, 'base_stat_total' => 288]);

    expect($table->classify($ditto))->toBe(RarityTier::Rare);
});

it('redistribui os pesos do slot garantido sem o tier comum', function () {
    $table = new RarityTable;
    $weights = $table->weights(guaranteedMinRarity: true);

    expect($weights)->not->toHaveKey(RarityTier::Common->value)
        ->and(array_sum($weights))->toEqualWithDelta(100.0, 0.0001);
});

it('distribui os tiers do slot livre perto dos pesos configurados em amostra grande', function () {
    $table = new RarityTable;
    $sampleSize = 200_000;
    $counts = array_fill_keys(array_map(fn ($tier) => $tier->value, RarityTier::cases()), 0);

    for ($i = 0; $i < $sampleSize; $i++) {
        $tier = $table->rollTier(mt_rand() / mt_getrandmax());
        $counts[$tier->value]++;
    }

    foreach (config('game.rarity.weights') as $tier => $weight) {
        $expected = $sampleSize * ($weight / 100);
        $tolerance = max($expected * 0.1, 200);

        expect($counts[$tier])
            ->toBeGreaterThan($expected - $tolerance)
            ->toBeLessThan($expected + $tolerance);
    }
})->group('distribution');

it('nunca sorteia comum no slot garantido', function () {
    $table = new RarityTable;

    for ($i = 0; $i < 5000; $i++) {
        $tier = $table->rollTier(mt_rand() / mt_getrandmax(), guaranteedMinRarity: true);

        expect($tier)->not->toBe(RarityTier::Common);
    }
});
