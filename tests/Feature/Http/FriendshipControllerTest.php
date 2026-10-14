<?php

declare(strict_types=1);

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('manda pedido e volta pra tela', function () {
    $user = User::factory()->create();
    User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);

    $this->actingAs($user)->from('/amigos')->post('/amigos/pedidos', ['handle' => 'Lin#0741'])
        ->assertRedirect('/amigos')
        ->assertSessionHasNoErrors();

    expect(Friendship::count())->toBe(1);
});

it('devolve o erro da regra no campo handle', function () {
    $this->actingAs(User::factory()->create())->from('/amigos')->post('/amigos/pedidos', ['handle' => 'Ninguem#0000'])
        ->assertRedirect('/amigos')
        ->assertSessionHasErrors(['handle' => 'Não achei ninguém com esse nickname#tag']);
});

it('só quem recebeu aceita o pedido', function () {
    $friendship = Friendship::factory()->create();

    $this->actingAs($friendship->requester)->post("/amigos/pedidos/{$friendship->id}/aceitar")->assertForbidden();
    $this->actingAs(User::factory()->create())->post("/amigos/pedidos/{$friendship->id}/aceitar")->assertForbidden();
    $this->actingAs($friendship->addressee)->from('/amigos')->post("/amigos/pedidos/{$friendship->id}/aceitar")->assertRedirect('/amigos');

    expect($friendship->refresh()->status)->toBe(FriendshipStatus::Accepted);
});

it('não aceita de novo uma amizade já aceita', function () {
    $friendship = Friendship::factory()->accepted()->create();

    $this->actingAs($friendship->addressee)->post("/amigos/pedidos/{$friendship->id}/aceitar")->assertForbidden();
});

it('recusa, cancela ou desfaz apagando a linha, só pelos dois lados', function (string $who) {
    $friendship = Friendship::factory()->create();
    $actor = $who === 'requester' ? $friendship->requester : $friendship->addressee;

    $this->actingAs(User::factory()->create())->delete("/amigos/{$friendship->id}")->assertForbidden();
    $this->actingAs($actor)->from('/amigos')->delete("/amigos/{$friendship->id}")->assertRedirect('/amigos');

    expect(Friendship::count())->toBe(0);
})->with(['requester', 'addressee']);

it('dá 404 ao mexer num pedido que já sumiu', function () {
    $friendship = Friendship::factory()->create();
    $addressee = $friendship->addressee;
    $friendship->delete();

    $this->actingAs($addressee)->post("/amigos/pedidos/{$friendship->id}/aceitar")->assertNotFound();
    $this->actingAs($addressee)->delete("/amigos/{$friendship->id}")->assertNotFound();
});

it('compartilha a contagem de pedidos recebidos pendentes', function () {
    $user = User::factory()->create();
    Friendship::factory()->count(2)->create(['addressee_id' => $user->id]);
    Friendship::factory()->accepted()->create(['addressee_id' => $user->id]);
    Friendship::factory()->create(['requester_id' => $user->id]);

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('pendingFriendRequests', 2));
});
