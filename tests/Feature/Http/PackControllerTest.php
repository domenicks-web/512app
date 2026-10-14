<?php

declare(strict_types=1);

use App\Models\Opening;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Species::factory()->count(3)->create();
    Species::factory()->uncommon()->count(2)->create();
    Species::factory()->rare()->create();
    Species::factory()->legendary()->create();
});

it('mostra o idle com o hash da seed já comprometida', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/pack')->assertInertia(fn (Assert $page) => $page
        ->component('pack/Show')
        ->where('serverSeedHash', $user->fresh()->next_server_seed_hash)
        ->where('opening', null)
        ->where('nextPackAt', null)
        ->missing('serverSeed')
    );

    expect($user->fresh()->next_server_seed_hash)->toBe(hash('sha256', $user->fresh()->next_server_seed));
});

it('a explosão não conta o que tem nas bolas', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/pack/abrir')->assertOk();

    expect(array_keys($response->json()))->toBe(['openingId', 'slots', 'serverSeedHash', 'nextPackAt'])
        ->and($response->json('slots'))->toBe(5);
});

it('dá 409 com a hora do próximo pack quando está em cooldown', function () {
    $user = User::factory()->create(['next_pack_at' => now()->addHours(3)]);

    $this->actingAs($user)->postJson('/pack/abrir')->assertStatus(409)->assertJsonStructure(['availableAt']);
});

it('revela a bola clicada e a seed só depois da última', function () {
    $user = User::factory()->create();
    $openingId = $this->actingAs($user)->postJson('/pack/abrir')->json('openingId');

    $this->postJson("/pack/{$openingId}/bolas/1")
        ->assertOk()
        ->assertJsonPath('specimen.slot', 1)
        ->assertJsonPath('isComplete', false)
        ->assertJsonPath('receipt', null);

    foreach ([2, 3, 4] as $slot) {
        $this->postJson("/pack/{$openingId}/bolas/{$slot}")->assertOk();
    }

    $this->postJson("/pack/{$openingId}/bolas/5")
        ->assertJsonPath('isComplete', true)
        ->assertJsonPath('receipt.serverSeed', Opening::find($openingId)->server_seed);
});

it('recarregar no meio mostra só as bolas abertas', function () {
    $user = User::factory()->create();
    $openingId = $this->actingAs($user)->postJson('/pack/abrir')->json('openingId');
    $this->postJson("/pack/{$openingId}/bolas/4");

    $this->get('/pack')->assertInertia(fn (Assert $page) => $page
        ->where('opening.id', $openingId)
        ->where('opening.slots.0.specimen', null)
        ->where('opening.slots.3.specimen.slot', 4)
        ->where('opening.slots.4.specimen', null)
        ->where('opening.receipt', null)
    );
});

it('não deixa abrir bola do pack de outro jogador', function () {
    $owner = User::factory()->create();
    $openingId = $this->actingAs($owner)->postJson('/pack/abrir')->json('openingId');

    $this->actingAs(User::factory()->create())->postJson("/pack/{$openingId}/bolas/1")->assertNotFound();
    $this->actingAs($owner)->postJson("/pack/{$openingId}/bolas/6")->assertNotFound();
});

it('valida a client seed', function (string $seed, bool $valid) {
    $response = $this->actingAs(User::factory()->create())->patchJson('/pack/client-seed', ['client_seed' => $seed]);

    $valid ? $response->assertOk() : $response->assertUnprocessable();
})->with([
    'normal' => ['minha-seed-123', true],
    'com espaço' => ['minha seed', false],
    'longa' => [str_repeat('a', 25), false],
    'vazia' => ['', false],
]);
