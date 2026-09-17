<?php

declare(strict_types=1);

use App\Actions\CompleteOnboarding;
use App\Actions\Data\CompleteOnboardingData;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preenche nickname, tag, data de nascimento e avatar', function () {
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();

    $result = (new CompleteOnboarding)->handle($user, new CompleteOnboardingData(
        nickname: 'Ramon',
        birthdate: now()->subYears(25),
        avatarSpeciesId: $species->id,
    ));

    expect($result->nickname)->toBe('Ramon')
        ->and($result->tag)->toMatch('/^\d{4}$/')
        ->and($result->avatar_species_id)->toBe($species->id)
        ->and($result->hasCompletedProfile())->toBeTrue();
});

it('gera tags diferentes pra nicknames repetidos', function () {
    User::factory()->create(['nickname' => 'Ramon', 'tag' => '0001']);
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();

    $result = (new CompleteOnboarding)->handle($user, new CompleteOnboardingData(
        nickname: 'Ramon',
        birthdate: now()->subYears(25),
        avatarSpeciesId: $species->id,
    ));

    expect($result->tag)->not->toBe('0001');
});
