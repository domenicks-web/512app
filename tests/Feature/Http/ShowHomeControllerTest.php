<?php

declare(strict_types=1);

use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('mostra o treinador com o sprite do parceiro escolhido', function () {
    $species = Species::factory()->create(['sprite_path' => 'species/25/sprite.png']);
    $user = User::factory()->create(['nickname' => 'Lin', 'tag' => '6401', 'avatar_species_id' => $species->id]);

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('trainer.nickname', 'Lin')
            ->where('trainer.tag', '6401')
            ->where('trainer.avatarSpritePath', 'species/25/sprite.png'));
});

it('conta espécies distintas da coleção, não espécimes', function () {
    $user = User::factory()->create();
    [$pikachu, $rattata] = Species::factory()->count(2)->create();
    Specimen::factory()->count(3)->for($user)->create(['species_id' => $pikachu->id]);
    Specimen::factory()->for($user)->create(['species_id' => $rattata->id]);
    Specimen::factory()->create(['species_id' => $rattata->id]);

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('collection.ownedSpecies', 2)
            ->where('collection.totalSpecies', Species::count()));
});

it('manda os valores provisórios da Home a partir do config', function () {
    config(['game.home.placeholders.trade_proposals' => 7]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('placeholders.trade_proposals', 7));
});
