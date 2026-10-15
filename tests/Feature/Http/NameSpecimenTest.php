<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../Actions/Trades/helpers.php';

uses(RefreshDatabase::class);

it('dá o apelido pelo POST', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner);

    $this->actingAs($owner)->post("/especime/{$specimen->id}/apelido", ['nickname' => '  João  '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($specimen->fresh()->nickname)->toBe('João');
});

it('recusa vazio, só espaços e mais de 16 caracteres', function (string $nickname) {
    $owner = User::factory()->create();
    $specimen = owned($owner);

    $this->actingAs($owner)->post("/especime/{$specimen->id}/apelido", ['nickname' => $nickname])
        ->assertSessionHasErrors('nickname');

    expect($specimen->fresh()->nickname)->toBeNull();
})->with(['', '   ', str_repeat('a', 17)]);

it('aceita 16 caracteres com acento e emoji', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner);

    $this->actingAs($owner)->post("/especime/{$specimen->id}/apelido", ['nickname' => 'Ãçéíõú⚡🔥aaaaaaa'])
        ->assertSessionHasNoErrors();

    expect($specimen->fresh()->nickname)->toBe('Ãçéíõú⚡🔥aaaaaaa');
});

it('devolve o erro da regra no campo nickname', function () {
    $specimen = owned(User::factory()->create());

    $this->actingAs(User::factory()->create())->post("/especime/{$specimen->id}/apelido", ['nickname' => 'João'])
        ->assertSessionHasErrors(['nickname' => 'Esse espécime não é seu.']);

    expect($specimen->fresh()->nickname)->toBeNull();
});
