<?php

declare(strict_types=1);

use App\Actions\GenerateUserTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('nunca devolve uma tag já usada pelo mesmo nickname', function () {
    foreach (range(0, 8) as $tag) {
        User::factory()->create(['nickname' => 'Lin', 'tag' => (string) $tag]);
    }

    $action = new GenerateUserTag(digits: 1);

    $tag = $action->handle('Lin');

    expect($tag)->toBe('9');
});

it('não se importa com colisão de tag pra um nickname diferente', function () {
    User::factory()->create(['nickname' => 'Lin', 'tag' => '0']);

    $action = new GenerateUserTag(digits: 1);

    $tag = $action->handle('Vex');

    expect($tag)->toBeIn(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']);
});
