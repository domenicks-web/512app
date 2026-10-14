<?php

declare(strict_types=1);

use App\Actions\FindTopSpecimens;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve os melhores na ordem shiny, raridade, IV e menor mint, com limite', function () {
    $user = User::factory()->create();
    $common = Species::factory()->create();
    $legend = Species::factory()->legendary()->create();
    $make = fn (array $attributes) => Specimen::factory()->for($user)->for(Opening::factory()->for($user))->create($attributes);
    $commonShiny = $make(['species_id' => $common->id, 'is_shiny' => true]);
    $legendLowIv = $make(['species_id' => $legend->id, 'iv_hp' => 0, 'iv_atk' => 0, 'iv_def' => 0, 'iv_spa' => 0, 'iv_spd' => 0, 'iv_spe' => 0]);
    $legendHighIv = $make(['species_id' => $legend->id, 'iv_hp' => 31, 'iv_atk' => 31]);
    $make(['species_id' => $common->id]);

    $top = (new FindTopSpecimens)->handle($user, 3);

    expect(array_column($top, 'id'))->toBe([$commonShiny->id, $legendHighIv->id, $legendLowIv->id])
        ->and($top[0])->toMatchArray(['speciesId' => $common->id, 'isShiny' => true, 'spritePath' => $common->sprite_shiny_path]);
});

it('desempata pelo menor mint', function () {
    $user = User::factory()->create();
    $species = Species::factory()->create();
    $same = ['species_id' => $species->id, 'iv_hp' => 1, 'iv_atk' => 1, 'iv_def' => 1, 'iv_spa' => 1, 'iv_spd' => 1, 'iv_spe' => 1];
    $late = Specimen::factory()->for($user)->for(Opening::factory()->for($user))->create([...$same, 'mint_number' => 900]);
    $early = Specimen::factory()->for($user)->for(Opening::factory()->for($user))->create([...$same, 'mint_number' => 800]);

    expect(array_column((new FindTopSpecimens)->handle($user, 2), 'id'))->toBe([$early->id, $late->id]);
});

it('ignora espécime fechado e devolve vazio sem espécimes', function () {
    $user = User::factory()->create();
    Specimen::factory()->for($user)->for(Opening::factory()->for($user))->unrevealed()->create();

    expect((new FindTopSpecimens)->handle($user, 6))->toBe([]);
});
