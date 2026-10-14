<?php

declare(strict_types=1);

use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function comparisonSpecimen(User $owner, array $attributes = []): Specimen
{
    return Specimen::factory()->for($owner)->for(Opening::factory()->for($owner))->create($attributes);
}

beforeEach(function () {
    $this->species = Species::factory()->create(['name' => 'Pikachu']);
    $this->viewer = User::factory()->create(['nickname' => 'Eu']);
    $this->other = User::factory()->create(['nickname' => 'Lin']);
});

it('compara o meu com o de outro jogador da mesma espécie', function () {
    $mine = comparisonSpecimen($this->viewer, ['species_id' => $this->species->id]);
    $theirs = comparisonSpecimen($this->other, ['species_id' => $this->species->id]);

    $this->actingAs($this->viewer)->get("/comparar/{$mine->id}/{$theirs->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('compare/Show')
            ->where('mine.id', $mine->id)
            ->where('theirs.id', $theirs->id)
            ->where('mineOwner.nickname', 'Eu')
            ->where('theirsOwner.id', $this->other->id)
            ->where('theirsOwner.nickname', 'Lin')
            ->has('comparison.stats', 7)
            ->where('comparison.score.total', 7));
});

it('dá 404 quando não dá pra comparar', function (string $case) {
    $mine = comparisonSpecimen($this->viewer, ['species_id' => $this->species->id]);
    $theirs = comparisonSpecimen($this->other, ['species_id' => $this->species->id]);

    $url = match ($case) {
        'outra espécie' => "/comparar/{$mine->id}/".comparisonSpecimen($this->other)->id,
        'o meu é de outro' => "/comparar/{$theirs->id}/{$mine->id}",
        'fechado' => "/comparar/{$mine->id}/".comparisonSpecimen($this->other, ['species_id' => $this->species->id, 'revealed_at' => null])->id,
        'não existe' => "/comparar/{$mine->id}/99999",
    };

    $this->actingAs($this->viewer)->get($url)->assertNotFound();
})->with(['outra espécie', 'o meu é de outro', 'fechado', 'não existe']);

it('os dois podem ser meus', function () {
    $a = comparisonSpecimen($this->viewer, ['species_id' => $this->species->id]);
    $b = comparisonSpecimen($this->viewer, ['species_id' => $this->species->id]);

    $this->actingAs($this->viewer)->get("/comparar/{$a->id}/{$b->id}")->assertOk();
});

it('lista só os meus daquela espécie pra trocar o lado de cá', function () {
    $mine = comparisonSpecimen($this->viewer, ['species_id' => $this->species->id]);
    $another = comparisonSpecimen($this->viewer, ['species_id' => $this->species->id]);
    comparisonSpecimen($this->viewer);
    comparisonSpecimen($this->other, ['species_id' => $this->species->id]);
    $theirs = comparisonSpecimen($this->other, ['species_id' => $this->species->id]);

    $this->actingAs($this->viewer)->get("/comparar/{$mine->id}/{$theirs->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('choices', 2)
            ->where('choices', fn ($choices) => collect($choices)->pluck('id')->sort()->values()->all() === collect([$mine->id, $another->id])->sort()->values()->all()
                && collect($choices)->firstWhere('id', $mine->id)['isCurrent'] === true
                && collect($choices)->firstWhere('id', $another->id)['isCurrent'] === false));
});
