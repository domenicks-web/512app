<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('desloga o usuário e redireciona pro login', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

it('exige login pra deslogar', function () {
    $response = $this->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
