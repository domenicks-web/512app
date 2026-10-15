<?php

declare(strict_types=1);

use App\Actions\Trades\AcceptTrade;
use App\Actions\Trades\DeclineTrade;
use App\Actions\Trades\MarkTradeSeen;
use App\Enums\TradeCancelReason;
use App\Enums\TradeStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Actions/Trades/helpers.php';

uses(RefreshDatabase::class);

it('separa recebidas, enviadas e concluídas do ponto de vista de quem vê', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $theirs = owned($b);
    propose($a, $b, [$mine->id], [$theirs->id], 'bora');
    $expired = propose($b, $a, [owned($b)->id], [owned($a)->id]);
    $expired->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($b)->get('/trocas')
        ->assertInertia(fn (Assert $page) => $page
            ->component('trades/Index')
            ->where('tab', 'recebidas')
            ->has('received', 1)
            ->where('received.0.state', 'open')
            ->where('received.0.other.id', $a->id)
            ->where('received.0.give.0.specimenId', $theirs->id)
            ->where('received.0.receive.0.specimenId', $mine->id)
            ->where('received.0.message', 'bora')
            ->has('sent', 1)
            ->where('sent.0.state', 'expired')
            ->has('done', 0)
            ->where('ceremony', null)
            ->where('expiresAfterHours', 72)
            ->where('pendingTrades', 1));
});

it('abre a cerimônia pra quem propôs até ele ver, e reabre por ?troca= sem marcar', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(AcceptTrade::class)->handle($trade, $b);

    $this->actingAs($a)->get('/trocas')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ceremony.tradeId', $trade->id)
            ->where('ceremony.markSeen', true)
            ->has('ceremony.received', 1)
            ->has('ceremony.given', 1));

    app(MarkTradeSeen::class)->handle($trade, $a);

    $this->actingAs($a)->get('/trocas')->assertInertia(fn (Assert $page) => $page->where('ceremony', null));
    $this->actingAs($a)->get("/trocas?troca={$trade->id}")
        ->assertInertia(fn (Assert $page) => $page->where('ceremony.tradeId', $trade->id)->where('ceremony.markSeen', false));
});

it('marca espécie nova na Pokédex só quando quem recebe não tinha outra', function () {
    [$a, $b] = friends();
    $given = owned($a);
    $received = owned($b);
    owned($a, ['species_id' => $received->species_id]);
    $trade = propose($a, $b, [$given->id], [$received->id]);
    app(AcceptTrade::class)->handle($trade, $b);

    $this->actingAs($b)->get("/trocas?troca={$trade->id}")
        ->assertInertia(fn (Assert $page) => $page->where('ceremony.newSpeciesIds', [$given->species_id]));
    $this->actingAs($a)->get("/trocas?troca={$trade->id}")
        ->assertInertia(fn (Assert $page) => $page->where('ceremony.newSpeciesIds', []));
});

it('não mostra a cerimônia de troca alheia', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(AcceptTrade::class)->handle($trade, $b);

    $this->actingAs(User::factory()->create())->get("/trocas?troca={$trade->id}")
        ->assertInertia(fn (Assert $page) => $page->where('ceremony', null));
});

it('esconde encerradas mais velhas que o prazo', function () {
    [$a, $b] = friends();
    $old = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(AcceptTrade::class)->handle($old, $b);
    $this->travel(8)->days();

    $this->actingAs($b)->get('/trocas?aba=concluidas')
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'concluidas')->has('done', 0));
});

it('avisa no dock e na aba os desfechos que quem propôs ainda não viu', function () {
    [$a, $b] = friends();
    $declined = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(DeclineTrade::class)->handle($declined, $b);
    $fallen = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    $fallen->update(['status' => TradeStatus::Cancelled, 'cancel_reason' => TradeCancelReason::Unavailable, 'responded_at' => now()]);
    $byMe = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    $byMe->update(['status' => TradeStatus::Cancelled, 'cancel_reason' => TradeCancelReason::ByProposer, 'responded_at' => now()]);
    propose($b, $a, [owned($b)->id], [owned($a)->id]);

    $this->actingAs($a)->get('/trocas')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tradeAlerts', 3)
            ->where('newCounts.enviadas', 1)
            ->where('newCounts.concluidas', 1));

    $this->actingAs($a)->get('/trocas?aba=concluidas')
        ->assertInertia(fn (Assert $page) => $page->where('done.0.isNew', true));
    $this->actingAs($a)->get('/trocas?aba=concluidas')
        ->assertInertia(fn (Assert $page) => $page->where('done.0.isNew', false)->where('tradeAlerts', 2));

    $this->actingAs($a)->get('/trocas?aba=enviadas');
    $this->actingAs($a)->get('/trocas')
        ->assertInertia(fn (Assert $page) => $page->where('tradeAlerts', 1)->where('newCounts.enviadas', 0));
    $this->actingAs($b)->get('/trocas')->assertInertia(fn (Assert $page) => $page->where('tradeAlerts', 0));
});

it('conta a troca aceita no dock até a cerimônia tocar', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    app(AcceptTrade::class)->handle($trade, $b);

    $this->actingAs($a)->get('/')->assertInertia(fn (Assert $page) => $page->where('tradeAlerts', 1));
    app(MarkTradeSeen::class)->handle($trade, $a);
    $this->actingAs($a)->get('/')->assertInertia(fn (Assert $page) => $page->where('tradeAlerts', 0));
});
