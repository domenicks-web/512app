<?php

declare(strict_types=1);

use App\Enums\TradeStatus;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../Actions/Trades/helpers.php';

uses(RefreshDatabase::class);

it('propõe pela rota e cai em enviadas', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $theirs = owned($b);

    $this->actingAs($a)->post('/trocas', [
        'friend_id' => $b->id,
        'offered' => [$mine->id],
        'requested' => [$theirs->id],
        'message' => 'bora',
    ])->assertRedirect('/trocas?aba=enviadas');

    expect(Trade::query()->where('from_user_id', $a->id)->count())->toBe(1);
});

it('valida o formato antes da regra', function () {
    [$a, $b] = friends();

    $this->actingAs($a)->post('/trocas', ['friend_id' => $b->id, 'offered' => 'x'])
        ->assertSessionHasErrors(['offered', 'requested']);
});

it('aceita pela rota e abre a cerimônia', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);

    $this->actingAs($b)->post("/trocas/{$trade->id}/aceitar")->assertRedirect("/trocas?troca={$trade->id}");

    expect($trade->fresh()->status)->toBe(TradeStatus::Accepted);
});

it('aceitar vencida volta com erro e não move nada', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $trade = propose($a, $b, [$mine->id], [owned($b)->id]);
    $this->travel(73)->hours();

    $this->actingAs($b)->from('/trocas')->post("/trocas/{$trade->id}/aceitar")
        ->assertRedirect('/trocas')
        ->assertSessionHasErrors('trade');

    expect($mine->fresh()->user_id)->toBe($a->id);
});

it('recusa, cancela, contrapropõe e marca visto pelas rotas', function () {
    [$a, $b] = friends();
    $one = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    $two = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    $mine = owned($a);
    $theirs = owned($b);
    $three = propose($a, $b, [$mine->id], [$theirs->id]);

    $this->actingAs($b)->post("/trocas/{$one->id}/recusar")->assertRedirect();
    $this->actingAs($a)->post("/trocas/{$two->id}/cancelar")->assertRedirect();
    $this->actingAs($b)->post("/trocas/{$three->id}/contrapropor", [
        'offered' => [$theirs->id],
        'requested' => [$mine->id],
    ])->assertRedirect('/trocas?aba=enviadas');

    expect($one->fresh()->status)->toBe(TradeStatus::Declined)
        ->and($two->fresh()->status)->toBe(TradeStatus::Cancelled)
        ->and($three->fresh()->status)->toBe(TradeStatus::Declined);
});

it('exige login', function () {
    $this->post('/trocas')->assertRedirect('/login');
});
