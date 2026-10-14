<?php

declare(strict_types=1);

use App\Enums\TradeItemSide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

it('cria a proposta com os itens dos dois lados e validade de 72h', function () {
    $this->travelTo(now()->startOfSecond());
    [$a, $b] = friends();
    $mine = owned($a);
    $theirs = owned($b);

    $trade = propose($a, $b, [$mine->id], [$theirs->id], '  bora?  ');

    expect($trade->from_user_id)->toBe($a->id)
        ->and($trade->to_user_id)->toBe($b->id)
        ->and($trade->message)->toBe('bora?')
        ->and($trade->expires_at->equalTo(now()->addHours(72)))->toBeTrue()
        ->and($trade->items()->where('side', TradeItemSide::Offered)->pluck('specimen_id')->all())->toBe([$mine->id])
        ->and($trade->items()->where('side', TradeItemSide::Requested)->pluck('specimen_id')->all())->toBe([$theirs->id]);
});

it('só deixa propor pra amigo', function () {
    [$a, $b] = User::factory()->count(2)->create();

    propose($a, $b, [owned($a)->id], [owned($b)->id]);
})->throws(ValidationException::class, 'Só dá pra trocar com amigos.');

it('exige de 1 a 3 espécimes de cada lado', function (int $offered, int $requested) {
    [$a, $b] = friends();

    propose(
        $a,
        $b,
        collect(range(1, $offered))->map(fn () => owned($a)->id)->all(),
        collect(range(1, $requested))->map(fn () => owned($b)->id)->all(),
    );
})->with([[4, 1], [1, 4]])->throws(ValidationException::class, 'Escolha de 1 a 3 espécimes de cada lado.');

it('não aceita lado vazio', function () {
    [$a, $b] = friends();

    propose($a, $b, [], [owned($b)->id]);
})->throws(ValidationException::class, 'Escolha de 1 a 3 espécimes de cada lado.');

it('barra espécime oferecido que não é de quem propõe', function () {
    [$a, $b] = friends();

    propose($a, $b, [owned($b)->id], [owned($b)->id]);
})->throws(ValidationException::class, 'Algum espécime oferecido não é seu.');

it('barra espécime pedido que não é do amigo', function () {
    [$a, $b] = friends();

    propose($a, $b, [owned($a)->id], [owned(User::factory()->create())->id]);
})->throws(ValidationException::class);

it('barra espécime de bola ainda fechada', function () {
    [$a, $b] = friends();
    $closed = owned($b, ['revealed_at' => null]);

    propose($a, $b, [owned($a)->id], [$closed->id]);
})->throws(ValidationException::class);

it('barra espécime repetido na mesma proposta', function () {
    [$a, $b] = friends();
    $mine = owned($a);

    propose($a, $b, [$mine->id, $mine->id], [owned($b)->id]);
})->throws(ValidationException::class, 'Um espécime não pode entrar duas vezes.');

it('barra a mesma proposta aberta duas vezes', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $theirs = owned($b);
    propose($a, $b, [$mine->id], [$theirs->id]);

    propose($a, $b, [$mine->id], [$theirs->id]);
})->throws(ValidationException::class, 'Você já propôs essa troca.');

it('limita as propostas abertas de quem envia', function () {
    config(['game.trades.max_pending_outgoing' => 1]);
    [$a, $b] = friends();
    propose($a, $b, [owned($a)->id], [owned($b)->id]);

    propose($a, $b, [owned($a)->id], [owned($b)->id]);
})->throws(ValidationException::class, 'Você já tem 1 propostas esperando resposta.');
