<?php

declare(strict_types=1);

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lista os amigos aceitos nas duas direções, sem pedidos pendentes', function () {
    $user = User::factory()->create();
    $asked = User::factory()->create();
    $askedMe = User::factory()->create();
    $pending = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $asked->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $askedMe->id, 'addressee_id' => $user->id]);
    Friendship::factory()->create(['requester_id' => $user->id, 'addressee_id' => $pending->id]);
    Friendship::factory()->accepted()->create();

    expect($user->friendIds())->toEqualCanonicalizing([$asked->id, $askedMe->id]);
});

it('acha a linha entre duas pessoas em qualquer direção', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $friendship = Friendship::factory()->create(['requester_id' => $b->id, 'addressee_id' => $a->id]);

    expect(Friendship::query()->between($a, $b)->first()?->id)->toBe($friendship->id)
        ->and($friendship->otherThan($a)->id)->toBe($b->id);
});
