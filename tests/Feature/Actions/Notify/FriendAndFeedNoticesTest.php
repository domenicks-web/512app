<?php

declare(strict_types=1);

use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Friends\BefriendInviter;
use App\Actions\Friends\SendFriendRequest;
use App\Actions\Pack\RevealSlot;
use App\Events\PlayerNotified;
use App\Models\Invite;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake([PlayerNotified::class]));

/**
 * @return list<string>
 */
function kindsOn(string $channel): array
{
    return Event::dispatched(PlayerNotified::class)
        ->map(fn (array $args) => $args[0])
        ->filter(fn (PlayerNotified $e): bool => $e->channel === $channel)
        ->map(fn (PlayerNotified $e): string => $e->notice->kind)
        ->values()
        ->all();
}

it('avisa o pedido de amizade e o aceite', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $request = app(SendFriendRequest::class)->handle($a, "{$b->nickname}#{$b->tag}");
    app(AcceptFriendRequest::class)->handle($request);

    expect(kindsOn("users.{$b->id}"))->toBe(['friend_request'])
        ->and(kindsOn("users.{$a->id}"))->toBe(['friend_accepted']);
});

it('pedido cruzado vira aceite pra quem pediu primeiro', function () {
    [$a, $b] = User::factory()->count(2)->create();
    app(SendFriendRequest::class)->handle($a, "{$b->nickname}#{$b->tag}");
    app(SendFriendRequest::class)->handle($b, "{$a->nickname}#{$a->tag}");

    expect(kindsOn("users.{$a->id}"))->toBe(['friend_accepted'])
        ->and(kindsOn("users.{$b->id}"))->toBe(['friend_request']);
});

it('a amizade automática com quem convidou não avisa ninguém', function () {
    $inviter = User::factory()->create();
    $invite = Invite::factory()->create(['created_by' => $inviter->id]);
    $guest = User::factory()->create(['invited_by' => $invite->id]);

    app(BefriendInviter::class)->handle($guest);

    Event::assertNotDispatched(PlayerNotified::class);
});

it('avisa o grupo quando a bola revela um shiny, uma vez só', function () {
    $opener = User::factory()->create();
    $opening = Opening::factory()->for($opener)->create();
    $specimen = Specimen::factory()->unrevealed()->for($opener)->for($opening)
        ->create(['slot' => 1, 'is_shiny' => true]);

    app(RevealSlot::class)->handle($opening, 1);
    app(RevealSlot::class)->handle($opening->fresh(), 1);

    expect(kindsOn('group'))->toBe(['feed_highlight']);
    Event::assertDispatched(PlayerNotified::class, fn (PlayerNotified $e): bool => $e->notice->actorId === $opener->id);
});

it('não avisa o grupo por espécime comum que não é shiny', function () {
    $opener = User::factory()->create();
    $opening = Opening::factory()->for($opener)->create();
    $common = Species::factory()->create(['rarity_tier' => 'comum']);
    Specimen::factory()->unrevealed()->for($opener)->for($opening)->for($common)
        ->create(['slot' => 1, 'is_shiny' => false]);

    app(RevealSlot::class)->handle($opening, 1);

    expect(kindsOn('group'))->toBe([]);
});
