<?php

declare(strict_types=1);

use App\Broadcasting\GroupChannel;
use App\Broadcasting\UserChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deixa só o próprio jogador ouvir o canal dele', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect((new UserChannel)->join($user, $user->id))->toBeTrue()
        ->and((new UserChannel)->join($other, $user->id))->toBeFalse();
});

it('deixa o grupo só pra quem completou o perfil', function () {
    $done = User::factory()->create();
    $pending = User::factory()->create(['nickname' => null]);

    expect((new GroupChannel)->join($done))->toBeTrue()
        ->and((new GroupChannel)->join($pending))->toBeFalse();
});
