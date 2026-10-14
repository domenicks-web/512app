<?php

declare(strict_types=1);

use App\Actions\CompleteOnboarding;
use App\Actions\Data\CompleteOnboardingData;
use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\Invite;
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

it('sorteia outra tag quando a da prévia foi ocupada no meio-tempo', function () {
    User::factory()->create(['nickname' => 'Ramon', 'tag' => '6401']);
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();

    $result = (new CompleteOnboarding)->handle($user, new CompleteOnboardingData(
        nickname: 'Ramon',
        birthdate: now()->subYears(25),
        avatarSpeciesId: $species->id,
        previewedTag: '6401',
    ));

    expect($result->tag)->toMatch('/^\d{4}$/')->not->toBe('6401');
});

it('vira amigo de quem criou o convite ao completar o perfil', function () {
    $inviter = User::factory()->create();
    $invite = Invite::factory()->create(['created_by' => $inviter->id]);
    $user = User::factory()->withoutProfile()->create(['invited_by' => $invite->id]);

    (new CompleteOnboarding)->handle($user, new CompleteOnboardingData(
        nickname: 'Bia',
        birthdate: now()->subYears(25),
        avatarSpeciesId: Species::factory()->create()->id,
    ));

    expect($user->friendIds())->toBe([$inviter->id]);
});

it('aceita o pedido pendente com quem convidou em vez de duplicar', function () {
    $inviter = User::factory()->create();
    $invite = Invite::factory()->create(['created_by' => $inviter->id]);
    $user = User::factory()->withoutProfile()->create(['invited_by' => $invite->id]);
    Friendship::factory()->create(['requester_id' => $inviter->id, 'addressee_id' => $user->id]);

    (new CompleteOnboarding)->handle($user, new CompleteOnboardingData(
        nickname: 'Bia',
        birthdate: now()->subYears(25),
        avatarSpeciesId: Species::factory()->create()->id,
    ));

    expect(Friendship::count())->toBe(1)
        ->and(Friendship::first()->status)->toBe(FriendshipStatus::Accepted);
});

it('não cria amizade sem convite', function () {
    $user = User::factory()->withoutProfile()->create(['invited_by' => null]);

    (new CompleteOnboarding)->handle($user, new CompleteOnboardingData(
        nickname: 'Bia',
        birthdate: now()->subYears(25),
        avatarSpeciesId: Species::factory()->create()->id,
    ));

    expect(Friendship::count())->toBe(0);
});
