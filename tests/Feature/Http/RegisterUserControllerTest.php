<?php

declare(strict_types=1);

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'nickname' => 'Lin',
        'email' => 'lin@example.com',
        'password' => 'segredo123',
        'password_confirmation' => 'segredo123',
        'birthdate' => '2000-01-01',
        'invite_code' => 'RAMON-7X2K',
    ], $overrides);
}

it('cadastra e autentica o jogador quando o convite é válido', function () {
    Notification::fake();
    Invite::factory()->create(['code' => 'RAMON-7X2K']);

    $response = $this->post('/cadastro', validRegistrationPayload());

    $response->assertRedirect(route('home'));
    $this->assertAuthenticated();
    expect(User::where('email', 'lin@example.com')->exists())->toBeTrue();
});

it('rejeita cadastro sem código de convite', function () {
    $response = $this->post('/cadastro', validRegistrationPayload(['invite_code' => '']));

    $response->assertSessionHasErrors('invite_code');
    $this->assertGuest();
});

it('devolve erro no campo de convite quando o código já foi esgotado', function () {
    Invite::factory()->exhausted()->create(['code' => 'RAMON-7X2K']);

    $response = $this->post('/cadastro', validRegistrationPayload());

    $response->assertSessionHasErrors('invite_code');
    $this->assertGuest();
});
