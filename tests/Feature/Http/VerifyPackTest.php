<?php

declare(strict_types=1);

use App\Actions\Pack\OpenPack;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
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

it('pede login', function () {
    $this->get('/verificar')->assertRedirect('/login');
    $this->postJson('/verificar')->assertUnauthorized();
});

it('abre a tela já preenchida com o que vier na url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/verificar?server_seed=abc&server_seed_hash=def&client_seed=minha&nonce=7')
        ->assertInertia(fn (Assert $page) => $page
            ->component('verify/Show')
            ->where('prefill.serverSeed', 'abc')
            ->where('prefill.serverSeedHash', 'def')
            ->where('prefill.clientSeed', 'minha')
            ->where('prefill.nonce', '7')
        );
});

it('refaz a conta e chega nos mesmos espécimes que o pack gravou', function () {
    $user = User::factory()->create();
    $opening = (new OpenPack)->handle($user);
    $opening = Opening::findOrFail($opening->id);

    $response = $this->actingAs($user)->postJson('/verificar', [
        'server_seed' => $opening->server_seed,
        'server_seed_hash' => $opening->server_seed_hash,
        'client_seed' => $opening->client_seed,
        'nonce' => $opening->nonce,
    ])->assertOk()->assertJsonPath('hashMatches', true);

    $stored = Specimen::withoutGlobalScopes()->where('opening_id', $opening->id)->orderBy('slot')->get();
    $slots = $response->json('slots');

    expect($slots)->toHaveCount(5);

    foreach ($stored as $index => $specimen) {
        expect($slots[$index])
            ->slot->toBe($specimen->slot)
            ->seed->toBe($specimen->seed)
            ->speciesId->toBe($specimen->species_id)
            ->isShiny->toBe($specimen->is_shiny)
            ->sizeClass->toBe($specimen->size_class->value)
            ->nature->toBe($specimen->nature->label())
            ->ivs->toBe([$specimen->iv_hp, $specimen->iv_atk, $specimen->iv_def, $specimen->iv_spa, $specimen->iv_spd, $specimen->iv_spe]);
    }
});

it('diz quando o segredo não bate com o lacre', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/verificar', [
        'server_seed' => 'segredo',
        'server_seed_hash' => hash('sha256', 'outro-segredo'),
        'client_seed' => 'minha',
        'nonce' => 0,
    ])->assertOk()
        ->assertJsonPath('hashMatches', false)
        ->assertJsonPath('computedHash', hash('sha256', 'segredo'));
});

it('sem lacre não diz se bate, só faz a conta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/verificar', [
        'server_seed' => 'segredo',
        'client_seed' => 'minha',
        'nonce' => 0,
    ])->assertOk()
        ->assertJsonPath('hashMatches', null)
        ->assertJsonCount(5, 'slots');
});

it('a mesma conta dá sempre o mesmo resultado', function () {
    $user = User::factory()->create();
    $input = ['server_seed' => 'segredo', 'client_seed' => 'minha', 'nonce' => 2];

    $first = $this->actingAs($user)->postJson('/verificar', $input)->json('slots');
    $second = $this->actingAs($user)->postJson('/verificar', $input)->json('slots');

    expect($first)->toBe($second);
});

it('valida os campos', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/verificar', [
        'server_seed' => '',
        'server_seed_hash' => 'nao-e-hash',
        'client_seed' => 'com espaço',
        'nonce' => -1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['server_seed', 'server_seed_hash', 'client_seed', 'nonce']);
});
