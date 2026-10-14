<?php

declare(strict_types=1);

use App\Models\Friendship;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function trainerSpecimen(User $owner, array $attributes = []): Specimen
{
    return Specimen::factory()->for($owner)->for(Opening::factory()->for($owner))->create($attributes);
}

it('pede login', function () {
    $this->get('/treinador/1')->assertRedirect('/login');
});

it('dá 404 pra quem não completou o perfil e pra id que não existe', function () {
    $viewer = User::factory()->create();
    $incomplete = User::factory()->withoutProfile()->create();

    $this->actingAs($viewer)->get("/treinador/{$incomplete->id}")->assertNotFound();
    $this->actingAs($viewer)->get('/treinador/99999')->assertNotFound();
});

it('mostra quem é o treinador e os números dele', function () {
    [$a, $b] = Species::factory()->count(2)->create();
    $owner = User::factory()->create(['nickname' => 'Lin', 'tag' => '0042', 'created_at' => '2026-09-15 12:00:00']);
    trainerSpecimen($owner, ['species_id' => $a->id, 'is_shiny' => true]);
    trainerSpecimen($owner, ['species_id' => $b->id]);
    trainerSpecimen($owner, ['species_id' => $b->id])->forceFill(['revealed_at' => null])->save();

    $this->actingAs(User::factory()->create())->get("/treinador/{$owner->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('trainer/Show')
            ->where('trainer.id', $owner->id)
            ->where('trainer.nickname', 'Lin')
            ->where('trainer.tag', '0042')
            ->where('trainer.memberSince', 'set/2026')
            ->where('trainer.isViewer', false)
            ->where('stats.rankingPosition', 1)
            ->where('stats.shinyCount', 1)
            ->where('stats.openedPacks', 3)
            ->where('collection.ownedSpecies', 2)
            ->has('team', 2)
            ->where('team.0.isShiny', true));
});

it('quem não tem nada fica sem posição, com time vazio', function () {
    Species::factory()->count(2)->create();
    $owner = User::factory()->create();

    $this->actingAs(User::factory()->create())->get("/treinador/{$owner->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.rankingPosition', null)
            ->where('stats.shinyCount', 0)
            ->where('stats.openedPacks', 0)
            ->where('collection.ownedSpecies', 0)
            ->has('team', 0));
});

it('limita o time ao tamanho do config', function () {
    config(['game.profile.team_size' => 2]);
    $owner = User::factory()->create();
    $species = Species::factory()->create();
    foreach (range(1, 4) as $ignored) {
        trainerSpecimen($owner, ['species_id' => $species->id]);
    }

    $this->actingAs(User::factory()->create())->get("/treinador/{$owner->id}")
        ->assertInertia(fn (Assert $page) => $page->has('team', 2));
});

it('diz o estado da amizade de quem vê', function () {
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    $asked = User::factory()->create();
    $asking = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->create(['requester_id' => $viewer->id, 'addressee_id' => $asked->id]);
    $incoming = Friendship::factory()->create(['requester_id' => $asking->id, 'addressee_id' => $viewer->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $friend->id, 'addressee_id' => $viewer->id]);

    $state = fn (User $owner) => $this->actingAs($viewer)->get("/treinador/{$owner->id}");

    $state($viewer)->assertInertia(fn (Assert $page) => $page->where('friendship.state', 'self')->where('trainer.isViewer', true));
    $state($stranger)->assertInertia(fn (Assert $page) => $page->where('friendship.state', 'none')->where('friendship.friendshipId', null));
    $state($asked)->assertInertia(fn (Assert $page) => $page->where('friendship.state', 'outgoing'));
    $state($asking)->assertInertia(fn (Assert $page) => $page->where('friendship.state', 'incoming')->where('friendship.friendshipId', $incoming->id));
    $state($friend)->assertInertia(fn (Assert $page) => $page->where('friendship.state', 'friends'));
});

it('a ficha traz só os espécimes do dono e o melhor de quem vê', function () {
    $species = Species::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $ownerSpecimen = trainerSpecimen($owner, ['species_id' => $species->id]);
    trainerSpecimen($viewer, ['species_id' => $species->id]);
    $viewerShiny = trainerSpecimen($viewer, ['species_id' => $species->id, 'is_shiny' => true]);

    $this->actingAs($viewer)->getJson("/treinador/{$owner->id}/colecao/{$species->id}")
        ->assertOk()
        ->assertJsonCount(1, 'specimens')
        ->assertJsonPath('specimens.0.id', $ownerSpecimen->id)
        ->assertJsonPath('viewerSpecimenId', $viewerShiny->id);

    $other = Species::factory()->create();
    trainerSpecimen($owner, ['species_id' => $other->id]);

    $this->actingAs($viewer)->getJson("/treinador/{$owner->id}/colecao/{$other->id}")
        ->assertJsonPath('viewerSpecimenId', null);
});
