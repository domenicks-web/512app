<?php

declare(strict_types=1);

use App\Actions\Pack\OpenPack;
use App\Actions\Pack\RevealSlot;
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

it('revela uma bola de cada vez e só a server seed no fim', function () {
    $user = User::factory()->create();
    $opening = (new OpenPack)->handle($user);

    $specimen = app(RevealSlot::class)->handle($opening, 3);

    expect($specimen->slot)->toBe(3)
        ->and($specimen->revealed_at)->not->toBeNull()
        ->and(Specimen::pluck('id')->all())->toBe([$specimen->id])
        ->and($opening->fresh()->isSlotOpened(3))->toBeTrue()
        ->and($opening->fresh()->revealed_at)->toBeNull();

    foreach ([1, 2, 4, 5] as $slot) {
        app(RevealSlot::class)->handle($opening, $slot);
    }

    expect($opening->fresh()->isComplete())->toBeTrue()
        ->and($opening->fresh()->revealed_at)->not->toBeNull()
        ->and(Specimen::count())->toBe(5);
});

it('abrir a mesma bola de novo devolve o mesmo espécime', function () {
    $opening = (new OpenPack)->handle(User::factory()->create());

    $first = app(RevealSlot::class)->handle($opening, 2);
    $again = app(RevealSlot::class)->handle($opening, 2);

    expect($again->id)->toBe($first->id)
        ->and($again->revealed_at->toDateTimeString())->toBe($first->revealed_at->toDateTimeString());
});
