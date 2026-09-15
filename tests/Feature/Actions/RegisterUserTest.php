<?php

declare(strict_types=1);

use App\Actions\Data\RegisterUserData;
use App\Actions\RegisterUser;
use App\Exceptions\InviteNotRedeemableException;
use App\Models\Invite;
use App\Models\InviteRedemption;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function registerUserData(array $overrides = []): RegisterUserData
{
    $defaults = [
        'nickname' => 'Lin',
        'email' => 'lin@example.com',
        'password' => 'segredo123',
        'birthdate' => now()->subYears(20),
        'inviteCode' => 'RAMON-7X2K',
    ];

    $data = array_merge($defaults, $overrides);

    return new RegisterUserData(...$data);
}

it('cria o usuário, resgata o convite e envia a verificação de email', function () {
    Notification::fake();

    $invite = Invite::factory()->create(['code' => 'RAMON-7X2K', 'max_uses' => 1]);

    $user = (new RegisterUser)->handle(registerUserData());

    expect($user->nickname)->toBe('Lin')
        ->and($user->tag)->toMatch('/^\d{4}$/')
        ->and($user->email)->toBe('lin@example.com')
        ->and(Hash::check('segredo123', $user->password))->toBeTrue()
        ->and($user->invited_by)->toBe($invite->id);

    $invite->refresh();
    expect($invite->uses_count)->toBe(1);

    expect(InviteRedemption::where('invite_id', $invite->id)->where('user_id', $user->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejeita convite inexistente', function () {
    (new RegisterUser)->handle(registerUserData(['inviteCode' => 'NAO-EXISTE']));
})->throws(InviteNotRedeemableException::class);

it('rejeita convite esgotado', function () {
    Invite::factory()->exhausted()->create(['code' => 'RAMON-7X2K']);

    (new RegisterUser)->handle(registerUserData());
})->throws(InviteNotRedeemableException::class);

it('rejeita convite expirado', function () {
    Invite::factory()->expired()->create(['code' => 'RAMON-7X2K', 'max_uses' => 1]);

    (new RegisterUser)->handle(registerUserData());
})->throws(InviteNotRedeemableException::class);

it('aceita convite sem limite de usos mesmo depois de vários resgates', function () {
    Notification::fake();

    $invite = Invite::factory()->unlimited()->create(['code' => 'RAMON-7X2K', 'uses_count' => 50]);

    $user = (new RegisterUser)->handle(registerUserData());

    expect($user->invited_by)->toBe($invite->id);
});
