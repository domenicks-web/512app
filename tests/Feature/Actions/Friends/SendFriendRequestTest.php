<?php

declare(strict_types=1);

use App\Actions\Friends\SendFriendRequest;
use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function sendRequest(User $sender, string $handle): Friendship
{
    return app(SendFriendRequest::class)->handle($sender, $handle);
}

function expectHandleError(callable $send, string $message): void
{
    try {
        $send();
        test()->fail('Esperava ValidationException');
    } catch (ValidationException $exception) {
        expect($exception->errors()['handle'][0])->toBe($message);
    }
}

it('cria um pedido pendente pra quem tem o nickname#tag', function () {
    $sender = User::factory()->create();
    $lin = User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);

    $friendship = sendRequest($sender, 'Lin#0741');

    expect($friendship->requester_id)->toBe($sender->id)
        ->and($friendship->addressee_id)->toBe($lin->id)
        ->and($friendship->status)->toBe(FriendshipStatus::Pending);
});

it('ignora espaço em volta e caixa do nickname', function () {
    $lin = User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);

    expect(sendRequest(User::factory()->create(), '  lIN#0741 ')->addressee_id)->toBe($lin->id);
});

it('recusa handle sem # ou com parte vazia', function (string $handle) {
    expectHandleError(fn () => sendRequest(User::factory()->create(), $handle), 'Use o formato nickname#1234');
})->with(['Lin', 'Lin#', '#0741', '']);

it('avisa quando ninguém tem aquele nickname#tag', function () {
    User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);

    expectHandleError(fn () => sendRequest(User::factory()->create(), 'Lin#0742'), 'Não achei ninguém com esse nickname#tag');
});

it('não deixa mandar pedido pra si mesmo', function () {
    $sender = User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);

    expectHandleError(fn () => sendRequest($sender, 'Lin#0741'), 'Esse é você');
});

it('avisa quando já são amigos, em qualquer direção', function () {
    $sender = User::factory()->create();
    $lin = User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);
    Friendship::factory()->accepted()->create(['requester_id' => $lin->id, 'addressee_id' => $sender->id]);

    expectHandleError(fn () => sendRequest($sender, 'Lin#0741'), 'Vocês já são amigos');
});

it('não duplica pedido já enviado', function () {
    $sender = User::factory()->create();
    User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);
    sendRequest($sender, 'Lin#0741');

    expectHandleError(fn () => sendRequest($sender, 'Lin#0741'), 'Você já mandou um pedido pra essa pessoa');
});

it('aceita na hora quando a outra pessoa já tinha mandado pedido', function () {
    $sender = User::factory()->create();
    $lin = User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);
    $theirs = Friendship::factory()->create(['requester_id' => $lin->id, 'addressee_id' => $sender->id]);

    $friendship = sendRequest($sender, 'Lin#0741');

    expect($friendship->id)->toBe($theirs->id)
        ->and($friendship->status)->toBe(FriendshipStatus::Accepted)
        ->and($friendship->accepted_at)->not->toBeNull()
        ->and(Friendship::count())->toBe(1);
});
