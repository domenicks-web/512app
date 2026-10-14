<?php

declare(strict_types=1);

use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve os espécimes revelados do jogador daquela espécie, shiny primeiro', function () {
    $user = User::factory()->create();
    $species = Species::factory()->create();
    $common = Specimen::factory()->create(['user_id' => $user->id, 'species_id' => $species->id, 'is_shiny' => false]);
    $shiny = Specimen::factory()->create(['user_id' => $user->id, 'species_id' => $species->id, 'is_shiny' => true]);
    Specimen::factory()->unrevealed()->create(['user_id' => $user->id, 'species_id' => $species->id]);
    Specimen::factory()->create(['species_id' => $species->id]); // de outro jogador

    $this->actingAs($user)->getJson("/colecao/{$species->id}")
        ->assertOk()
        ->assertJsonCount(2, 'specimens')
        ->assertJsonPath('specimens.0.id', $shiny->id)
        ->assertJsonPath('specimens.1.id', $common->id)
        ->assertJsonPath('specimens.0.name', $species->name);
});

it('admin libera o próximo pack de um jogador', function () {
    $player = User::factory()->create(['next_pack_at' => now()->addHours(5)]);

    $this->actingAs(User::factory()->create())->post("/admin/jogadores/{$player->id}/liberar-pack")->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post("/admin/jogadores/{$player->id}/liberar-pack")->assertRedirect();

    expect($player->fresh()->next_pack_at)->toBeNull();
});
