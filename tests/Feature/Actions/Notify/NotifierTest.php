<?php

declare(strict_types=1);

use App\Actions\Data\Notice;
use App\Actions\Notify\BuildNotice;
use App\Actions\Notify\Notifier;
use App\Events\PlayerNotified;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function sampleNotice(): Notice
{
    return new Notice(
        kind: 'trade_declined',
        dock: 'trades',
        title: 'Lin recusou sua troca',
        body: null,
        spritePath: 'sprites/1.png',
        tier: null,
        isShiny: false,
        href: '/trocas?aba=concluidas',
        reload: ['tradeAlerts'],
        actorId: 7,
    );
}

it('manda o aviso pro canal do jogador com o nome notice', function () {
    Event::fake([PlayerNotified::class]);

    app(Notifier::class)->toPlayer(42, sampleNotice());

    Event::assertDispatched(PlayerNotified::class, function (PlayerNotified $event): bool {
        return $event->broadcastOn()->name === 'private-users.42'
            && $event->broadcastAs() === 'notice'
            && $event->broadcastWith()['kind'] === 'trade_declined'
            && $event->broadcastWith()['actorId'] === 7;
    });
});

it('manda o aviso do grupo pro canal group', function () {
    Event::fake([PlayerNotified::class]);

    app(Notifier::class)->toGroup(sampleNotice());

    Event::assertDispatched(PlayerNotified::class, fn (PlayerNotified $event): bool => $event->broadcastOn()->name === 'private-group');
});

it('só transmite depois do commit e nada sai se a transação volta', function () {
    Event::fake([PlayerNotified::class]);

    try {
        DB::transaction(function (): void {
            app(Notifier::class)->toPlayer(1, sampleNotice());
            throw new RuntimeException('volta');
        });
    } catch (RuntimeException) {
    }

    Event::assertNotDispatched(PlayerNotified::class);
});

it('não derruba ninguém quando o broadcast falha', function () {
    Broadcast::extend('explode', fn () => new class extends Broadcaster
    {
        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = []): void
        {
            throw new BroadcastException('Reverb fora do ar');
        }
    });
    config(['broadcasting.connections.explode' => ['driver' => 'explode'], 'broadcasting.default' => 'explode']);

    app(Notifier::class)->toPlayer(1, sampleNotice());

    expect(true)->toBeTrue();
});

it('monta o aviso da proposta com o melhor espécime oferecido', function () {
    require_once __DIR__.'/../Trades/helpers.php';
    [$a, $b] = friends();
    $common = owned($a, ['is_shiny' => false]);
    $shiny = owned($a, ['is_shiny' => true]);
    $trade = propose($a, $b, [$common->id, $shiny->id], [owned($b)->id]);

    $notice = app(BuildNotice::class)->tradeReceived($trade);

    expect($notice->kind)->toBe('trade_received')
        ->and($notice->dock)->toBe('trades')
        ->and($notice->title)->toBe("{$a->nickname} quer trocar")
        ->and($notice->isShiny)->toBeTrue()
        ->and($notice->href)->toBe('/trocas?aba=recebidas')
        ->and($notice->actorId)->toBe($a->id);
});
