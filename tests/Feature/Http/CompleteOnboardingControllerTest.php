<?php

declare(strict_types=1);

use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mostra o onboarding pra quem ainda não tem perfil', function () {
    $user = User::factory()->withoutProfile()->create();

    $response = $this->actingAs($user)->get('/completar-perfil');

    $response->assertOk();
});

it('manda pra home quem já completou o perfil e tenta abrir o onboarding', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/completar-perfil');

    $response->assertRedirect(route('home'));
});

it('completa o perfil e redireciona pra home', function () {
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$species->id]]);

    $response = $this->actingAs($user)->post('/completar-perfil', [
        'nickname' => 'Ramon',
        'birthdate' => '2000-01-01',
        'avatar_species_id' => $species->id,
    ]);

    $response->assertRedirect(route('home'));
    expect($user->fresh()->nickname)->toBe('Ramon');
});

it('bloqueia quem já completou o perfil de reenviar o post e não altera os dados', function () {
    $user = User::factory()->create();
    $originalNickname = $user->nickname;
    $originalTag = $user->tag;
    $originalBirthdate = $user->birthdate;
    $originalAvatarSpeciesId = $user->avatar_species_id;
    $species = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$species->id]]);

    $response = $this->actingAs($user)->post('/completar-perfil', [
        'nickname' => 'OutroNick',
        'birthdate' => '1999-05-10',
        'avatar_species_id' => $species->id,
    ]);

    $response->assertRedirect(route('home'));

    $fresh = $user->fresh();
    expect($fresh->nickname)->toBe($originalNickname)
        ->and($fresh->tag)->toBe($originalTag)
        ->and($fresh->birthdate->equalTo($originalBirthdate))->toBeTrue()
        ->and($fresh->avatar_species_id)->toBe($originalAvatarSpeciesId);
});

it('rejeita avatar fora da lista permitida', function () {
    $user = User::factory()->withoutProfile()->create();
    $allowed = Species::factory()->create();
    $disallowed = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$allowed->id]]);

    $response = $this->actingAs($user)->post('/completar-perfil', [
        'nickname' => 'Ramon',
        'birthdate' => '2000-01-01',
        'avatar_species_id' => $disallowed->id,
    ]);

    $response->assertSessionHasErrors('avatar_species_id');
});
