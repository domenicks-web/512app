<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('considera o perfil completo quando o usuário tem nickname', function () {
    $user = User::factory()->create();

    expect($user->hasCompletedProfile())->toBeTrue();
});

it('considera o perfil incompleto quando falta o nickname', function () {
    $user = User::factory()->withoutProfile()->create();

    expect($user->hasCompletedProfile())->toBeFalse();
});
