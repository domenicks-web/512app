<?php

declare(strict_types=1);

use App\Actions\Pack\OpenPack;
use App\Exceptions\PackOnCooldownException;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Species::factory()->count(3)->create();
    Species::factory()->uncommon()->count(2)->create();
    Species::factory()->rare()->create();
    Species::factory()->legendary()->create();
});

it('abre o pack com a seed comprometida e grava 5 espécimes fechados', function () {
    $this->freezeTime();
    $user = User::factory()->create(['nonce' => 3, 'client_seed' => 'minha-seed']);
    $user->forceFill(['next_server_seed' => 'seed-do-servidor', 'next_server_seed_hash' => hash('sha256', 'seed-do-servidor')])->save();

    $opening = (new OpenPack)->handle($user);

    expect($opening->server_seed)->toBe('seed-do-servidor')
        ->and($opening->server_seed_hash)->toBe(hash('sha256', 'seed-do-servidor'))
        ->and($opening->client_seed)->toBe('minha-seed')
        ->and($opening->nonce)->toBe(3)
        ->and($opening->opened_slots)->toBe(0)
        ->and($opening->revealed_at)->toBeNull()
        ->and($opening->specimens()->count())->toBe(5)
        ->and($opening->specimens()->pluck('slot')->sort()->values()->all())->toBe([1, 2, 3, 4, 5])
        ->and(Specimen::count())->toBe(0); // fechados não aparecem pro resto do jogo

    $user->refresh();
    expect($user->nonce)->toBe(4)
        ->and($user->next_pack_at->toDateTimeString())->toBe(now()->addHours(24)->toDateTimeString())
        ->and($user->next_server_seed)->not->toBe('seed-do-servidor')
        ->and($user->next_server_seed_hash)->toBe(hash('sha256', $user->next_server_seed));
});

it('recusa abrir antes do cooldown acabar', function () {
    $user = User::factory()->create(['next_pack_at' => now()->addHour()]);

    (new OpenPack)->handle($user);
})->throws(PackOnCooldownException::class);

it('abre de novo quando o cooldown passa', function () {
    $user = User::factory()->create(['next_pack_at' => now()->subMinute()]);

    (new OpenPack)->handle($user);

    expect(Opening::count())->toBe(1);
});

it('numera o mint por espécie em sequência, contando os fechados', function () {
    $users = User::factory()->count(6)->create();

    foreach ($users as $user) {
        (new OpenPack)->handle($user);
    }

    $mintsBySpecies = Specimen::withoutGlobalScopes()->get()->groupBy('species_id')
        ->map(fn ($specimens) => $specimens->pluck('mint_number')->sort()->values()->all());

    foreach ($mintsBySpecies as $mints) {
        expect($mints)->toBe(range(1, count($mints)));
    }
});
