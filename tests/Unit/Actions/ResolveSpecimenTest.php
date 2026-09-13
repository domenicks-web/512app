<?php

declare(strict_types=1);

use App\Actions\ResolveSpecimen;
use App\Enums\SizeClass;
use App\Models\Species;

function randomSpecimenSeed(): string
{
    return bin2hex(random_bytes(32));
}

it('é determinístico: a mesma seed sempre produz o mesmo resultado', function () {
    $action = new ResolveSpecimen;
    $species = Species::factory()->make(['id' => 25]);
    $seed = randomSpecimenSeed();

    $first = $action->handle($seed, $species, mintNumber: 7);
    $second = $action->handle($seed, $species, mintNumber: 7);

    expect($second)->toEqual($first);
});

it('repassa seed, espécie e mint number sem alterar', function () {
    $action = new ResolveSpecimen;
    $species = Species::factory()->make(['id' => 94]);
    $seed = randomSpecimenSeed();

    $data = $action->handle($seed, $species, mintNumber: 42);

    expect($data->seed)->toBe($seed)
        ->and($data->speciesId)->toBe(94)
        ->and($data->mintNumber)->toBe(42);
});

it('produz IVs sempre entre 0 e 31 e o total bate com a soma', function () {
    $action = new ResolveSpecimen;
    $species = Species::factory()->make(['id' => 1]);

    for ($i = 0; $i < 500; $i++) {
        $data = $action->handle(randomSpecimenSeed(), $species, $i);

        foreach ([$data->ivHp, $data->ivAtk, $data->ivDef, $data->ivSpa, $data->ivSpd, $data->ivSpe] as $iv) {
            expect($iv)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(31);
        }

        expect($data->ivTotal())->toBe(
            $data->ivHp + $data->ivAtk + $data->ivDef + $data->ivSpa + $data->ivSpd + $data->ivSpe
        );
    }
});

it('mantém o size_roll entre 0 e 1 e coerente com a size_class', function () {
    $action = new ResolveSpecimen;
    $species = Species::factory()->make(['id' => 1]);
    $ranges = config('game.size.ranges');

    for ($i = 0; $i < 500; $i++) {
        $data = $action->handle(randomSpecimenSeed(), $species, $i);

        expect($data->sizeRoll)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);

        [$min, $max] = $ranges[$data->sizeClass->value];

        expect($data->sizeRoll)->toBeGreaterThanOrEqual($min);

        if ($data->sizeClass !== SizeClass::Xxl) {
            expect($data->sizeRoll)->toBeLessThan($max);
        }
    }
});

it('mantém a taxa de shiny numa amostra grande perto da taxa configurada', function () {
    $action = new ResolveSpecimen;
    $species = Species::factory()->make(['id' => 1]);
    $sampleSize = 200_000;
    $shinyCount = 0;

    for ($i = 0; $i < $sampleSize; $i++) {
        if ($action->handle(randomSpecimenSeed(), $species, $i)->isShiny) {
            $shinyCount++;
        }
    }

    $expected = $sampleSize * (float) config('game.shiny.rate');
    $tolerance = max($expected * 0.5, 30);

    expect($shinyCount)->toBeGreaterThan($expected - $tolerance)
        ->toBeLessThan($expected + $tolerance);
})->group('distribution');
