<?php

declare(strict_types=1);

use App\Http\Controllers\Onboarding\PreviewUserTagController;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('mostra o onboarding pra quem ainda não tem perfil', function () {
    $user = User::factory()->withoutProfile()->create();

    $response = $this->actingAs($user)->get('/completar-perfil');

    $response->assertOk();
});

it('devolve os avatares na ordem curada do config, não na ordem do banco', function () {
    $user = User::factory()->withoutProfile()->create();

    // Criados de propósito na ordem inversa da lista do config, pra provar
    // que a ordem devolvida vem do config e não do id/insert no banco.
    $second = Species::factory()->create();
    $first = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$first->id, $second->id]]);

    $response = $this->actingAs($user)->get('/completar-perfil');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('avatarOptions.0.id', $first->id)
        ->where('avatarOptions.1.id', $second->id)
    );
});

it('manda pra home quem já completou o perfil e tenta abrir o onboarding', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/completar-perfil');

    $response->assertRedirect(route('home'));
});

it('completa o perfil e responde sem conteúdo pro cliente animar a saída', function () {
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$species->id]]);

    $response = $this->actingAs($user)->postJson('/completar-perfil', [
        'nickname' => 'Ramon',
        'birthdate' => '2000-01-01',
        'avatar_species_id' => $species->id,
    ]);

    $response->assertNoContent();
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

    $response = $this->actingAs($user)->postJson('/completar-perfil', [
        'nickname' => 'OutroNick',
        'birthdate' => '1999-05-10',
        'avatar_species_id' => $species->id,
    ]);

    $response->assertNoContent();

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

    $response = $this->actingAs($user)->postJson('/completar-perfil', [
        'nickname' => 'Ramon',
        'birthdate' => '2000-01-01',
        'avatar_species_id' => $disallowed->id,
    ]);

    $response->assertJsonValidationErrors('avatar_species_id');
});

it('sorteia a prévia da tag no servidor e guarda na sessão', function () {
    $user = User::factory()->withoutProfile()->create();

    $response = $this->actingAs($user)->getJson('/completar-perfil/tag?nickname=Lin');

    $tag = $response->assertOk()->json('tag');
    expect($tag)->toMatch('/^\d{4}$/');
    $response->assertSessionHas(PreviewUserTagController::SESSION_KEY, ['nickname' => 'Lin', 'tag' => $tag]);
});

it('recusa prévia de tag pra nickname maior que o limite do config', function () {
    $user = User::factory()->withoutProfile()->create();
    $tooLong = str_repeat('a', config('game.onboarding.nickname_max_length') + 1);

    $this->actingAs($user)->getJson('/completar-perfil/tag?nickname='.$tooLong)
        ->assertJsonValidationErrors('nickname');
});

it('grava a tag que o jogador viu na prévia', function () {
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$species->id]]);

    $this->actingAs($user)
        ->withSession([PreviewUserTagController::SESSION_KEY => ['nickname' => 'Lin', 'tag' => '6401']])
        ->postJson('/completar-perfil', [
            'nickname' => 'Lin',
            'birthdate' => '2000-01-01',
            'avatar_species_id' => $species->id,
        ])
        ->assertNoContent();

    expect($user->fresh()->tag)->toBe('6401');
});

it('ignora a prévia quando o nickname enviado é outro', function () {
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$species->id]]);
    User::factory()->create(['nickname' => 'Ash', 'tag' => '6401']);

    $this->actingAs($user)
        ->withSession([PreviewUserTagController::SESSION_KEY => ['nickname' => 'Lin', 'tag' => '6401']])
        ->postJson('/completar-perfil', [
            'nickname' => 'Ash',
            'birthdate' => '2000-01-01',
            'avatar_species_id' => $species->id,
        ])
        ->assertNoContent();

    expect($user->fresh()->tag)->not->toBe('6401');
});
