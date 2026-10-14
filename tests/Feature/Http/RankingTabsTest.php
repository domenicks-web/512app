<?php

declare(strict_types=1);

use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Espécime numa abertura do próprio dono (o `specimenOf` já existe no
 * GroupScreensTest; função global com nome repetido quebra a suíte).
 *
 * @param  array<string, mixed>  $attributes
 */
function rankedSpecimen(User $owner, array $attributes = []): Specimen
{
    return Specimen::factory()->for($owner)->for(Opening::factory()->for($owner))->create($attributes);
}

it('cai na aba de espécies sem query ou com aba inválida', function (string $url) {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get($url)->assertInertia(fn (Assert $page) => $page
        ->component('ranking/Show')
        ->where('tab', 'especies'));
})->with(['/ranking', '/ranking?por=xyz']);

it('a aba de espécies mantém a ordem de hoje e manda o valor', function () {
    [$a, $b] = Species::factory()->count(2)->create();
    $lin = User::factory()->create(['nickname' => 'Lin']);
    $bia = User::factory()->create(['nickname' => 'Bia']);
    rankedSpecimen($lin, ['species_id' => $a->id]);
    rankedSpecimen($lin, ['species_id' => $b->id]);
    rankedSpecimen($bia, ['species_id' => $a->id]);

    $this->actingAs($bia)->get('/ranking?por=especies')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.nickname', 'Lin')
        ->where('rows.0.value', 2)
        ->where('rows.0.position', 1)
        ->where('rows.1.value', 1)
        ->where('rows.1.specimen', null));
});

it('ordena por shinies, empate divide a posição e zero fica sem posição', function () {
    $species = Species::factory()->create();
    $a = User::factory()->create(['nickname' => 'Ana']);
    $b = User::factory()->create(['nickname' => 'Bia']);
    $c = User::factory()->create(['nickname' => 'Cau']);
    rankedSpecimen($a, ['species_id' => $species->id, 'is_shiny' => true]);
    rankedSpecimen($b, ['species_id' => $species->id, 'is_shiny' => true]);
    rankedSpecimen($c, ['species_id' => $species->id]);
    rankedSpecimen($a, ['species_id' => $species->id, 'is_shiny' => true])->forceFill(['revealed_at' => null])->save();

    $this->actingAs($c)->get('/ranking?por=shinies')->assertInertia(fn (Assert $page) => $page
        ->where('tab', 'shinies')
        ->where('rows.0.value', 1)
        ->where('rows.0.position', 1)
        ->where('rows.1.value', 1)
        ->where('rows.1.position', 1)
        ->where('rows.2.nickname', 'Cau')
        ->where('rows.2.position', null));
});

it('ordena pelo maior IV de um espécime só e mostra qual é', function () {
    $pikachu = Species::factory()->create(['name' => 'Pikachu']);
    $strong = User::factory()->create(['nickname' => 'Forte']);
    $weak = User::factory()->create(['nickname' => 'Fraco']);
    $empty = User::factory()->create(['nickname' => 'Vazio']);
    rankedSpecimen($strong, ['species_id' => $pikachu->id, 'iv_hp' => 31, 'iv_atk' => 31, 'iv_def' => 31, 'iv_spa' => 31, 'iv_spd' => 31, 'iv_spe' => 31, 'is_shiny' => true]);
    rankedSpecimen($strong, ['species_id' => $pikachu->id, 'iv_hp' => 0, 'iv_atk' => 0, 'iv_def' => 0, 'iv_spa' => 0, 'iv_spd' => 0, 'iv_spe' => 0]);
    rankedSpecimen($weak, ['species_id' => $pikachu->id, 'iv_hp' => 1, 'iv_atk' => 1, 'iv_def' => 1, 'iv_spa' => 1, 'iv_spd' => 1, 'iv_spe' => 1]);

    $this->actingAs($empty)->get('/ranking?por=iv')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.nickname', 'Forte')
        ->where('rows.0.value', 186)
        ->where('rows.0.specimen.speciesName', 'Pikachu')
        ->where('rows.0.specimen.isShiny', true)
        ->where('rows.0.specimen.spritePath', $pikachu->sprite_shiny_path)
        ->where('rows.1.value', 6)
        ->where('rows.1.position', 2)
        ->where('rows.2.nickname', 'Vazio')
        ->where('rows.2.position', null)
        ->where('rows.2.specimen', null));
});

it('conta os primeiros do servidor (mint 1)', function () {
    [$a, $b] = Species::factory()->count(2)->create();
    $first = User::factory()->create(['nickname' => 'Pioneira']);
    $late = User::factory()->create(['nickname' => 'Atrasado']);
    rankedSpecimen($first, ['species_id' => $a->id, 'mint_number' => 1]);
    rankedSpecimen($first, ['species_id' => $b->id, 'mint_number' => 1]);
    rankedSpecimen($late, ['species_id' => $a->id, 'mint_number' => 2]);

    $this->actingAs($late)->get('/ranking?por=primeiros')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.nickname', 'Pioneira')
        ->where('rows.0.value', 2)
        ->where('rows.0.position', 1)
        ->where('rows.1.nickname', 'Atrasado')
        ->where('rows.1.position', null));
});

it('com o grupo todo zerado, toda aba renderiza sem posição', function (string $tab) {
    User::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())->get("/ranking?por={$tab}")->assertInertia(fn (Assert $page) => $page
        ->has('rows', 3)
        ->where('rows.0.position', null));
})->with(['especies', 'shinies', 'iv', 'primeiros']);

it('não muda o que a Home recebe do ranking', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('rankingTop')
        ->has('rankingPosition'));
});
