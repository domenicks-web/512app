<?php

declare(strict_types=1);

use App\Actions\Trades\AcceptTrade;
use App\Actions\Trades\CancelTrade;
use App\Actions\Trades\CounterTrade;
use App\Actions\Trades\DeclineTrade;
use App\Actions\Trades\MarkTradeSeen;
use App\Enums\TradeCancelReason;
use App\Enums\TradeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

it('o destinatário recusa', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);

    app(DeclineTrade::class)->handle($trade, $b);

    expect($trade->fresh()->status)->toBe(TradeStatus::Declined);
});

it('quem propôs não recusa a própria', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);

    app(DeclineTrade::class)->handle($trade, $a);
})->throws(ValidationException::class, 'Essa proposta não está mais pendente.');

it('quem propôs cancela, mesmo vencida', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    $this->travel(80)->hours();

    app(CancelTrade::class)->handle($trade, $a);

    expect($trade->fresh()->status)->toBe(TradeStatus::Cancelled)
        ->and($trade->fresh()->cancel_reason)->toBe(TradeCancelReason::ByProposer);
});

it('o destinatário não cancela', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);

    app(CancelTrade::class)->handle($trade, $b);
})->throws(ValidationException::class, 'Essa proposta não está mais pendente.');

it('a contraproposta recusa a original e cria a inversa ligada a ela', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $theirs = owned($b);
    $original = propose($a, $b, [$mine->id], [$theirs->id]);
    $extra = owned($a);

    $counter = app(CounterTrade::class)->handle($original, $b, [$theirs->id], [$mine->id, $extra->id], 'só com mais um');

    expect($original->fresh()->status)->toBe(TradeStatus::Declined)
        ->and($counter->from_user_id)->toBe($b->id)
        ->and($counter->to_user_id)->toBe($a->id)
        ->and($counter->counter_of_id)->toBe($original->id);
});

it('contraproposta inválida deixa a original pendente', function () {
    [$a, $b] = friends();
    $original = propose($a, $b, [owned($a)->id], [owned($b)->id]);

    expect(fn () => app(CounterTrade::class)->handle($original, $b, [owned($b)->id], [owned($b)->id], null))
        ->toThrow(ValidationException::class)
        ->and($original->fresh()->status)->toBe(TradeStatus::Pending);
});

it('marca como visto só pra quem propôs, numa troca aceita', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(AcceptTrade::class)->handle($trade, $b);

    app(MarkTradeSeen::class)->handle($trade, $b);
    expect($trade->fresh()->proposer_seen_at)->toBeNull();

    app(MarkTradeSeen::class)->handle($trade, $a);
    expect($trade->fresh()->proposer_seen_at)->not->toBeNull();
});
