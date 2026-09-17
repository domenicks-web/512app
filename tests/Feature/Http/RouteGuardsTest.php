<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redireciona deslogado pro login ao acessar a home', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

it('redireciona pro onboarding quando o perfil está incompleto', function () {
    $user = User::factory()->withoutProfile()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('onboarding.show'));
});

it('deixa passar quem já completou o perfil', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
});
