<?php

declare(strict_types=1);

use App\Enums\TradeStatus;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('considera aberta só a pendente que ainda não venceu', function () {
    $open = Trade::factory()->create();
    $expired = Trade::factory()->expired()->create();
    Trade::factory()->accepted()->create();

    expect(Trade::query()->open()->pluck('id')->all())->toBe([$open->id])
        ->and($open->isOpen())->toBeTrue()
        ->and($expired->isOpen())->toBeFalse()
        ->and($expired->state())->toBe('expired')
        ->and($open->state())->toBe('open');
});

it('vence em 72h por padrão', function () {
    $this->travelTo(now()->startOfSecond());
    $trade = Trade::factory()->create();

    expect($trade->expires_at->equalTo(now()->addHours(config('game.trades.expires_after_hours'))))->toBeTrue()
        ->and(config('game.trades.expires_after_hours'))->toBe(72)
        ->and($trade->status)->toBe(TradeStatus::Pending);
});
