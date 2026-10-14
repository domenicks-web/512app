<?php

declare(strict_types=1);

use App\Actions\Trades\AcceptTrade;
use App\Enums\TradeCancelReason;
use App\Enums\TradeStatus;
use App\Models\Friendship;
use App\Models\Species;
use App\Models\SpecimenTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

function accept($trade, User $user)
{
    return app(AcceptTrade::class)->handle($trade, $user);
}

it('troca os donos, grava o histórico e marca como aceita', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $theirs = owned($b);
    $trade = propose($a, $b, [$mine->id], [$theirs->id]);

    accept($trade, $b);

    expect($mine->fresh()->user_id)->toBe($b->id)
        ->and($theirs->fresh()->user_id)->toBe($a->id)
        ->and($trade->fresh()->status)->toBe(TradeStatus::Accepted)
        ->and($trade->fresh()->responded_at)->not->toBeNull()
        ->and(SpecimenTransfer::query()->where('specimen_id', $mine->id)->first()?->only(['from_user_id', 'to_user_id', 'trade_id']))
        ->toBe(['from_user_id' => $a->id, 'to_user_id' => $b->id, 'trade_id' => $trade->id]);
});

it('derruba as outras propostas abertas com os mesmos espécimes', function () {
    [$a, $b] = friends();
    $c = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $a->id, 'addressee_id' => $c->id]);
    $mine = owned($a);
    $toB = propose($a, $b, [$mine->id], [owned($b)->id]);
    $toC = propose($a, $c, [$mine->id], [owned($c)->id]);

    accept($toB, $b);

    expect($toC->fresh()->status)->toBe(TradeStatus::Cancelled)
        ->and($toC->fresh()->cancel_reason)->toBe(TradeCancelReason::Unavailable);
});

it('cai com "não disponível" quando um espécime já mudou de dono', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $trade = propose($a, $b, [$mine->id], [owned($b)->id]);
    $mine->forceFill(['user_id' => User::factory()->create()->id])->save();

    expect(fn () => accept($trade, $b))->toThrow(ValidationException::class, 'Algum espécime dessa troca não está mais disponível.')
        ->and($trade->fresh()->status)->toBe(TradeStatus::Cancelled)
        ->and($trade->fresh()->cancel_reason)->toBe(TradeCancelReason::Unavailable);
});

it('barra quem não é o destinatário', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);

    accept($trade, $a);
})->throws(ValidationException::class, 'Essa proposta não está mais pendente.');

it('barra proposta vencida e já respondida', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    $this->travel(73)->hours();

    expect(fn () => accept($trade, $b))->toThrow(ValidationException::class, 'Essa proposta não está mais pendente.');

    $this->travelBack();
    $other = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    accept($other, $b);

    expect(fn () => accept($other, $b))->toThrow(ValidationException::class, 'Essa proposta não está mais pendente.');
});

it('duas propostas pelo mesmo espécime: só a primeira aceita passa', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $first = propose($a, $b, [$mine->id], [owned($b)->id]);
    $second = propose($a, $b, [$mine->id], [owned($b)->id]);

    accept($first, $b);

    expect(fn () => accept($second, $b))->toThrow(ValidationException::class, 'Essa proposta não está mais pendente.')
        ->and($mine->fresh()->user_id)->toBe($b->id);
});

it('aceita mesmo se a amizade acabou depois da proposta', function () {
    [$a, $b] = friends();
    $trade = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    Friendship::query()->delete();

    expect(accept($trade, $b)->status)->toBe(TradeStatus::Accepted);
});

it('volta o avatar pro Pikachu de quem deu o espécime do avatar', function () {
    [$a, $b] = friends();
    // Espécie fora dos 15 avatares fixos: só vale se o jogador tem o espécime.
    $mine = owned($a, ['species_id' => Species::factory()->create(['id' => 9000])->id]);
    $a->forceFill(['avatar_species_id' => $mine->species_id, 'avatar_is_shiny' => false])->save();
    $trade = propose($a, $b, [$mine->id], [owned($b)->id]);

    accept($trade, $b);

    expect($a->fresh()->avatar_species_id)->toBe(config('game.onboarding.avatar_species_ids')[0]);
});
