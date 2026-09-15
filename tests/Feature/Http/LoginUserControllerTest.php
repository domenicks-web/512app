<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('autentica o jogador com credenciais válidas', function () {
    $user = User::factory()->create(['password' => Hash::make('segredo123')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'segredo123',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);
});

it('rejeita senha incorreta sem dizer qual campo errou', function () {
    $user = User::factory()->create(['password' => Hash::make('segredo123')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'senha-errada',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('bloqueia depois de muitas tentativas erradas pro mesmo email', function () {
    $user = User::factory()->create(['password' => Hash::make('segredo123')]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
    }

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'segredo123']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
