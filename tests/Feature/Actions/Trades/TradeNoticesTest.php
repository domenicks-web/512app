<?php

declare(strict_types=1);

use App\Actions\Trades\AcceptTrade;
use App\Actions\Trades\CancelTrade;
use App\Actions\Trades\CounterTrade;
use App\Actions\Trades\DeclineTrade;
use App\Events\PlayerNotified;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake([PlayerNotified::class]));

/**
 * @return list<string>
 */
function noticesTo(int $userId): array
{
    return Event::dispatched(PlayerNotified::class)
        ->map(fn (array $args) => $args[0])
        ->filter(fn (PlayerNotified $e): bool => $e->channel === "users.{$userId}")
        ->map(fn (PlayerNotified $e): string => $e->notice->kind)
        ->values()
        ->all();
}

it('avisa quem recebe a proposta e a contraproposta', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(CounterTrade::class)->handle($trade, $b, [owned($b)->id], [owned($a)->id], null);

    expect(noticesTo($b->id))->toBe(['trade_received'])
        ->and(noticesTo($a->id))->toBe(['trade_received']);
});

it('avisa quem propôs quando aceitam e quem perdeu a troca que caiu', function () {
    [$a, $b] = friends();
    $mine = owned($b);
    $trade = propose($a, $b, [owned($a)->id], [$mine->id]);
    $c = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $c->id, 'addressee_id' => $b->id]);
    propose($c, $b, [owned($c)->id], [$mine->id]);

    app(AcceptTrade::class)->handle($trade, $b);

    expect(noticesTo($a->id))->toContain('trade_accepted')
        ->and(noticesTo($c->id))->toContain('trade_fallen')
        ->and(noticesTo($b->id))->toBe(['trade_received', 'trade_received']);
});

it('avisa quem propôs quando o aceite falha por espécime que já saiu', function () {
    [$a, $b] = friends();
    $gone = owned($a);
    $trade = propose($a, $b, [$gone->id], [owned($b)->id]);
    $gone->forceFill(['user_id' => User::factory()->create()->id])->save();

    expect(fn () => app(AcceptTrade::class)->handle($trade, $b))->toThrow(ValidationException::class);
    expect(noticesTo($a->id))->toBe(['trade_fallen']);
});

it('avisa quem propôs da recusa, mas não avisa ninguém do cancelamento', function () {
    [$a, $b] = friends();
    $declined = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(DeclineTrade::class)->handle($declined, $b);
    $cancelled = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(CancelTrade::class)->handle($cancelled, $a);

    expect(noticesTo($a->id))->toBe(['trade_declined'])
        ->and(noticesTo($b->id))->toBe(['trade_received', 'trade_received']);
});
