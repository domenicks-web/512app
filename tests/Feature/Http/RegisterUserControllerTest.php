<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ValidateInviteController;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'lin@example.com',
        'password' => 'segredo123',
        'password_confirmation' => 'segredo123',
    ], $overrides);
}

it('mostra só a porta fechada quando não há convite validado na sessão', function () {
    $this->get('/cadastro')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/Register')
            ->where('inviteCode', null));
});

it('guarda o convite na sessão e libera o formulário quando o código é válido', function () {
    Invite::factory()->create(['code' => 'RAMON-7X2K']);

    $this->postJson('/cadastro/convite', ['invite_code' => 'RAMON-7X2K'])
        ->assertOk()
        ->assertJson(['inviteCode' => 'RAMON-7X2K'])
        ->assertSessionHas(ValidateInviteController::SESSION_KEY, 'RAMON-7X2K');

    $this->get('/cadastro')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('inviteCode', 'RAMON-7X2K'));
});

it('recusa convite inexistente na etapa de validação', function () {
    $this->postJson('/cadastro/convite', ['invite_code' => 'NAO-EXISTE'])
        ->assertJsonValidationErrors('invite_code')
        ->assertSessionMissing(ValidateInviteController::SESSION_KEY);
});

it('recusa convite esgotado na etapa de validação', function () {
    Invite::factory()->exhausted()->create(['code' => 'RAMON-7X2K']);

    $this->postJson('/cadastro/convite', ['invite_code' => 'RAMON-7X2K'])
        ->assertJsonValidationErrors('invite_code')
        ->assertSessionMissing(ValidateInviteController::SESSION_KEY);
});

it('volta pra porta fechada se o convite da sessão deixou de valer', function () {
    Invite::factory()->exhausted()->create(['code' => 'RAMON-7X2K']);

    $this->withSession([ValidateInviteController::SESSION_KEY => 'RAMON-7X2K'])
        ->get('/cadastro')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('inviteCode', null))
        ->assertSessionMissing(ValidateInviteController::SESSION_KEY);
});

it('esquece o convite quando o jogador pede pra trocar', function () {
    $this->withSession([ValidateInviteController::SESSION_KEY => 'RAMON-7X2K'])
        ->deleteJson('/cadastro/convite')
        ->assertNoContent()
        ->assertSessionMissing(ValidateInviteController::SESSION_KEY);
});

it('cadastra e autentica o jogador quando o convite validado ainda vale', function () {
    Notification::fake();
    Invite::factory()->create(['code' => 'RAMON-7X2K']);

    $response = $this->withSession([ValidateInviteController::SESSION_KEY => 'RAMON-7X2K'])
        ->postJson('/cadastro', validRegistrationPayload());

    $response->assertNoContent();
    $response->assertSessionMissing(ValidateInviteController::SESSION_KEY);
    $this->assertAuthenticated();
    expect(User::where('email', 'lin@example.com')->exists())->toBeTrue();
});

it('rejeita cadastro sem convite validado antes', function () {
    $response = $this->postJson('/cadastro', validRegistrationPayload());

    $response->assertJsonValidationErrors('invite_code');
    $this->assertGuest();
});

it('ignora o código mandado no corpo do formulário', function () {
    Invite::factory()->create(['code' => 'RAMON-7X2K']);

    $response = $this->postJson('/cadastro', validRegistrationPayload(['invite_code' => 'RAMON-7X2K']));

    $response->assertJsonValidationErrors('invite_code');
    $this->assertGuest();
});

it('volta pra porta fechada quando o convite se esgota entre a validação e o cadastro', function () {
    Invite::factory()->exhausted()->create(['code' => 'RAMON-7X2K']);

    $response = $this->withSession([ValidateInviteController::SESSION_KEY => 'RAMON-7X2K'])
        ->postJson('/cadastro', validRegistrationPayload());

    $response->assertJsonValidationErrors('invite_code');
    $response->assertSessionMissing(ValidateInviteController::SESSION_KEY);
    $this->assertGuest();
});
