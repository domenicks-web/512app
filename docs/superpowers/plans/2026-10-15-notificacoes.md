# Fase 4B, notificações em tempo real: plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** trocas, amizade e o destaque do feed chegam na hora como bolha animada com som saindo do dock, via Laravel Reverb.

**Architecture:** o servidor monta uma `Notice` pronta (texto, sprite, destino, props a recarregar) e transmite um único evento `PlayerNotified` (`ShouldBroadcastNow`, depois do commit, com erro só no log) em `users.{id}` ou `group`. O front assina os dois canais no `GroupLayout` (persistente), enfileira as bolhas, toca o som e recarrega só as props afetadas.

**Tech Stack:** Laravel 13, Laravel Reverb, Pest, Inertia v3 + Vue 3, laravel-echo + pusher-js, WAAPI (`resources/js/lib/motion.ts`).

**Spec:** `docs/superpowers/specs/2026-10-15-notificacoes-design.md`

## Global Constraints

- `declare(strict_types=1)` em todo PHP; Pint preset Laravel; PHPStan verde (`composer ci:check`).
- Comentários e textos em português; vocabulário `Species`/`Specimen` (nunca "card", "pokemon" solto).
- Nada de número mágico: tiers do feed vêm de `config('game.group.feed_highlight_tiers')`.
- O front nunca decide conteúdo: a `Notice` sai pronta do servidor.
- Testes e CI com `BROADCAST_CONNECTION=null` (já está no `phpunit.xml`); nada pode exigir o Reverb rodando.
- Falha ao transmitir nunca derruba a ação de negócio.
- Commits em português, imperativo, sem prefixo de conventional commits.

## Review Focus

- Reverb parado: propor, aceitar, recusar, pedir amizade e revelar a bola continuam funcionando (teste com broadcaster que explode, Task 2).
- Aceite que falha por espécime indisponível: quem propôs aquela troca recebe `trade_fallen`, mesmo com a exceção de validação pro aceitante (Task 3).
- Bola já revelada sendo revelada de novo (recarregar a página do pack) não notifica o grupo de novo (Task 4).
- Pedido de amizade cruzado (B pede pra A, que já tinha pedido pra B) vira aceite: notifica `friend_accepted` pra quem pediu primeiro, não `friend_request` (Task 4).
- Quem abriu o lendário recebe o próprio aviso do grupo: o payload leva `actorId` e o front ignora o próprio (Task 2 testa o `actorId`; Task 6 filtra).

---

## Parte do Kimi (backend), worktree `../512app-worktrees/notificacoes-back`

### Task 1: Reverb, canais e processo de dev

**Files:**
- Modify: `composer.json` (via `composer require laravel/reverb`)
- Create/Modify: `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php`, `bootstrap/app.php` (via `install:broadcasting`)
- Create: `app/Broadcasting/UserChannel.php`, `app/Broadcasting/GroupChannel.php`
- Modify: `app/Providers/AppServiceProvider.php` (processo `reverb` no `php artisan dev`)
- Modify: `.env.example`
- Test: `tests/Feature/Broadcasting/ChannelsTest.php`

**Interfaces:**
- Produces: canais privados `users.{id}` (`UserChannel::join(User $user, int $id): bool`) e `group` (`GroupChannel::join(User $user): bool`).

- [ ] **Step 1: Instalar o Reverb sem a parte de Node** (o front é do Claude)

```bash
composer require laravel/reverb --no-interaction
php artisan install:broadcasting --help   # confira os flags desta versão
php artisan install:broadcasting --reverb --no-interaction --without-node
```

Se o comando ainda criar `resources/js/echo.js` ou mexer em `package.json`/`resources/js/app.ts`, **desfaça essas mudanças de JS** (`git checkout -- package.json package-lock.json resources/js` e apague arquivos JS novos). Confira que `bootstrap/app.php` ganhou `channels: __DIR__.'/../routes/channels.php'` no `withRouting`. Não comite o `.env` (é local).

- [ ] **Step 2: Teste dos canais (falha)**

```php
<?php

declare(strict_types=1);

use App\Broadcasting\GroupChannel;
use App\Broadcasting\UserChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deixa só o próprio jogador ouvir o canal dele', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect((new UserChannel)->join($user, $user->id))->toBeTrue()
        ->and((new UserChannel)->join($other, $user->id))->toBeFalse();
});

it('deixa o grupo só pra quem completou o perfil', function () {
    $done = User::factory()->create();
    $pending = User::factory()->create(['nickname' => null]);

    expect((new GroupChannel)->join($done))->toBeTrue()
        ->and((new GroupChannel)->join($pending))->toBeFalse();
});
```

Run: `php artisan test --compact tests/Feature/Broadcasting/ChannelsTest.php` → FAIL (classe não existe). Se a factory não aceitar `nickname => null`, use `forceFill(['nickname' => null])->save()`.

- [ ] **Step 3: Canais**

`app/Broadcasting/UserChannel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

/** Canal privado de cada jogador (`users.{id}`): avisos de troca e amizade. */
class UserChannel
{
    public function join(User $user, int $id): bool
    {
        return $user->id === $id;
    }
}
```

`app/Broadcasting/GroupChannel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

/** Canal do grupo (`group`): destaque do feed. Só quem já está jogando. */
class GroupChannel
{
    public function join(User $user): bool
    {
        return $user->hasCompletedProfile();
    }
}
```

`routes/channels.php` (substitui o conteúdo gerado):

```php
<?php

declare(strict_types=1);

use App\Broadcasting\GroupChannel;
use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', UserChannel::class);
Broadcast::channel('group', GroupChannel::class);
```

- [ ] **Step 4: Processo do Reverb no `php artisan dev`** (só local)

No `boot()` do `AppServiceProvider`:

```php
if ($this->app->environment('local')) {
    // Ouve na rede toda pro celular conectar pelo IP (ver DEV_LAN_HOST).
    DevCommands::artisan('reverb:start --host=0.0.0.0 --port=8080', 'reverb');
}
```

com `use Illuminate\Foundation\DevCommands;`. Confira com `php artisan dev:list` que aparece `reverb` (se o próprio pacote já registrar um processo `reverb`, não duplique: use o dele e anote no relatório).

- [ ] **Step 5: `.env.example`**

Troque `BROADCAST_CONNECTION=log` por `BROADCAST_CONNECTION=reverb` e acrescente (se o install não pôs):

```
REVERB_APP_ID=512app
REVERB_APP_KEY=512app-key
REVERB_APP_SECRET=512app-secret
REVERB_HOST="${DEV_LAN_HOST}"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 6: Rodar** `php artisan test --compact tests/Feature/Broadcasting/ChannelsTest.php` → PASS. `composer ci:check` verde.

- [ ] **Step 7: Commit** `git add -A && git commit -m "Instala o Reverb com os canais do jogador e do grupo"`

### Task 2: `Notice`, `PlayerNotified` e `Notifier`

**Files:**
- Create: `app/Actions/Data/Notice.php`, `app/Events/PlayerNotified.php`, `app/Actions/Notify/Notifier.php`, `app/Actions/Notify/BuildNotice.php`
- Test: `tests/Feature/Actions/Notify/NotifierTest.php`

**Interfaces:**
- Produces:
  - `Notice` (readonly): `kind, dock, title, body, spritePath, tier, isShiny, href, reload, actorId`; `toArray(): array{kind: string, dock: string, title: string, body: ?string, spritePath: ?string, tier: ?string, isShiny: bool, href: string, reload: list<string>, actorId: ?int}`.
  - `PlayerNotified(string $channel, Notice $notice)`: `broadcastOn()` → `PrivateChannel($channel)`, `broadcastAs()` → `'notice'`, `broadcastWith()` → `$notice->toArray()`.
  - `Notifier::toPlayer(int $userId, Notice $notice): void` e `Notifier::toGroup(Notice $notice): void`. Os dois esperam o commit (`DB::afterCommit`) e engolem erro de broadcast com `report()`.
  - `BuildNotice`: `tradeReceived(Trade)`, `tradeAccepted(Trade)`, `tradeDeclined(Trade)`, `tradeFallen(Trade)`, `friendRequest(Friendship)`, `friendAccepted(Friendship)`, `feedHighlight(Specimen)`, cada um → `Notice`.

- [ ] **Step 1: Teste (falha)**

```php
<?php

declare(strict_types=1);

use App\Actions\Data\Notice;
use App\Actions\Notify\Notifier;
use App\Events\PlayerNotified;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
```

Run: `php artisan test --compact tests/Feature/Actions/Notify/NotifierTest.php` → FAIL.

- [ ] **Step 2: `Notice`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\Data;

/**
 * Um aviso em tempo real, pronto pra bolha do dock: o front só anima.
 * `dock` diz de qual ícone a bolha sai; `reload`, as props Inertia que a
 * tela aberta recarrega; `actorId`, quem causou (o front ignora o próprio
 * aviso do grupo).
 */
final readonly class Notice
{
    /**
     * @param  'trade_received'|'trade_accepted'|'trade_declined'|'trade_fallen'|'friend_request'|'friend_accepted'|'feed_highlight'  $kind
     * @param  'trades'|'friends'|'home'  $dock
     * @param  list<string>  $reload
     */
    public function __construct(
        public string $kind,
        public string $dock,
        public string $title,
        public ?string $body,
        public ?string $spritePath,
        public ?string $tier,
        public bool $isShiny,
        public string $href,
        public array $reload,
        public ?int $actorId,
    ) {}

    /**
     * @return array{kind: string, dock: string, title: string, body: ?string, spritePath: ?string, tier: ?string, isShiny: bool, href: string, reload: list<string>, actorId: ?int}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'dock' => $this->dock,
            'title' => $this->title,
            'body' => $this->body,
            'spritePath' => $this->spritePath,
            'tier' => $this->tier,
            'isShiny' => $this->isShiny,
            'href' => $this->href,
            'reload' => $this->reload,
            'actorId' => $this->actorId,
        ];
    }
}
```

- [ ] **Step 3: `PlayerNotified`**

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Data\Notice;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * O único evento em tempo real: um aviso pra um canal (`users.{id}` ou
 * `group`). Sai na hora (sem fila); quem dispara é o `Notifier`, depois do
 * commit.
 */
class PlayerNotified implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $channel,
        public readonly Notice $notice,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->channel);
    }

    public function broadcastAs(): string
    {
        return 'notice';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->notice->toArray();
    }
}
```

- [ ] **Step 4: `Notifier`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\Notify;

use App\Actions\Data\Notice;
use App\Events\PlayerNotified;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Entrega os avisos: espera a transação gravar e, se o Reverb estiver fora
 * do ar, só registra no log. Aviso nunca derruba a ação do jogo.
 */
class Notifier
{
    public function toPlayer(int $userId, Notice $notice): void
    {
        $this->send("users.{$userId}", $notice);
    }

    public function toGroup(Notice $notice): void
    {
        $this->send('group', $notice);
    }

    private function send(string $channel, Notice $notice): void
    {
        DB::afterCommit(function () use ($channel, $notice): void {
            try {
                event(new PlayerNotified($channel, $notice));
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
```

- [ ] **Step 5: `BuildNotice`** (os textos ficam todos aqui)

```php
<?php

declare(strict_types=1);

namespace App\Actions\Notify;

use App\Actions\Data\Notice;
use App\Enums\TradeItemSide;
use App\Models\Friendship;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\TradeItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Monta o aviso de cada acontecimento, do ponto de vista de quem recebe.
 * Todo texto da bolha nasce aqui.
 */
class BuildNotice
{
    private const TRADE_PROPS = ['tradeAlerts', 'pendingTrades', 'received', 'sent', 'done', 'newCounts'];

    private const FRIEND_PROPS = ['pendingFriendRequests', 'tradeAlerts', 'friends', 'incoming', 'outgoing'];

    private const TIER_ORDER = ['comum', 'incomum', 'raro', 'lendario'];

    /** Pra quem recebe a proposta: o melhor que vem pra ele em destaque. */
    public function tradeReceived(Trade $trade): Notice
    {
        $from = $trade->fromUser;
        $offered = $this->side($trade, TradeItemSide::Offered);
        $best = $this->best($offered);

        return new Notice(
            kind: 'trade_received',
            dock: 'trades',
            title: $trade->counter_of_id !== null
                ? "{$from->nickname} fez uma contraproposta"
                : "{$from->nickname} quer trocar",
            body: $this->names($offered).' ⇄ '.$this->names($this->side($trade, TradeItemSide::Requested)),
            spritePath: $this->sprite($best),
            tier: $best?->species->rarity_tier->value,
            isShiny: (bool) $best?->is_shiny,
            href: route('trades.index', ['aba' => 'recebidas'], false),
            reload: self::TRADE_PROPS,
            actorId: $from->id,
        );
    }

    /** Pra quem propôs: o que ele ganhou. A cerimônia toca ao abrir Trocas. */
    public function tradeAccepted(Trade $trade): Notice
    {
        $to = $trade->toUser;
        $requested = $this->side($trade, TradeItemSide::Requested);
        $best = $this->best($requested);

        return new Notice(
            kind: 'trade_accepted',
            dock: 'trades',
            title: "{$to->nickname} aceitou sua troca",
            body: 'Você recebeu '.$this->names($requested),
            spritePath: $this->sprite($best),
            tier: $best?->species->rarity_tier->value,
            isShiny: (bool) $best?->is_shiny,
            href: route('trades.index', [], false),
            reload: self::TRADE_PROPS,
            actorId: $to->id,
        );
    }

    public function tradeDeclined(Trade $trade): Notice
    {
        $to = $trade->toUser;

        return new Notice(
            kind: 'trade_declined',
            dock: 'trades',
            title: "{$to->nickname} recusou sua troca",
            body: null,
            spritePath: $to->avatarSpritePath(),
            tier: null,
            isShiny: false,
            href: route('trades.index', ['aba' => 'concluidas'], false),
            reload: self::TRADE_PROPS,
            actorId: $to->id,
        );
    }

    public function tradeFallen(Trade $trade): Notice
    {
        return new Notice(
            kind: 'trade_fallen',
            dock: 'trades',
            title: "Sua troca com {$trade->toUser->nickname} caiu",
            body: 'Um espécime foi trocado em outra',
            spritePath: $trade->toUser->avatarSpritePath(),
            tier: null,
            isShiny: false,
            href: route('trades.index', ['aba' => 'enviadas'], false),
            reload: self::TRADE_PROPS,
            actorId: null,
        );
    }

    public function friendRequest(Friendship $friendship): Notice
    {
        $requester = $friendship->requester;

        return new Notice(
            kind: 'friend_request',
            dock: 'friends',
            title: "{$requester->nickname} quer ser seu amigo",
            body: null,
            spritePath: $requester->avatarSpritePath(),
            tier: null,
            isShiny: false,
            href: route('friends.index', [], false),
            reload: self::FRIEND_PROPS,
            actorId: $requester->id,
        );
    }

    public function friendAccepted(Friendship $friendship): Notice
    {
        $addressee = $friendship->addressee;

        return new Notice(
            kind: 'friend_accepted',
            dock: 'friends',
            title: "{$addressee->nickname} aceitou seu pedido",
            body: 'Agora vocês são amigos',
            spritePath: $addressee->avatarSpritePath(),
            tier: null,
            isShiny: false,
            href: route('trainer.show', ['user' => $addressee->id], false),
            reload: self::FRIEND_PROPS,
            actorId: $addressee->id,
        );
    }

    /** Pro grupo: alguém tirou um shiny ou um tier do feed. */
    public function feedHighlight(Specimen $specimen): Notice
    {
        $opener = $specimen->opening->user;
        $name = $specimen->species->name.($specimen->is_shiny ? ' shiny' : '');

        return new Notice(
            kind: 'feed_highlight',
            dock: 'home',
            title: "{$opener->nickname} tirou {$name}!",
            body: $specimen->species->rarity_tier->label(),
            spritePath: $this->sprite($specimen),
            tier: $specimen->species->rarity_tier->value,
            isShiny: $specimen->is_shiny,
            href: route('feed.show', [], false),
            reload: ['feedHighlight'],
            actorId: $opener->id,
        );
    }

    /**
     * @return Collection<int, Specimen>
     */
    private function side(Trade $trade, TradeItemSide $side): Collection
    {
        return $trade->items()->with('specimen.species')->get()
            ->where('side', $side)
            ->map(fn (TradeItem $item): Specimen => $item->specimen)
            ->values();
    }

    /**
     * @param  Collection<int, Specimen>  $specimens
     */
    private function best(Collection $specimens): ?Specimen
    {
        return $specimens->sortByDesc(fn (Specimen $s): int => (int) $s->is_shiny * 10
            + (int) array_search($s->species->rarity_tier->value, self::TIER_ORDER, true))->first();
    }

    /**
     * @param  Collection<int, Specimen>  $specimens
     */
    private function names(Collection $specimens): string
    {
        return $specimens->map(fn (Specimen $s): string => $s->species->name)->join(', ');
    }

    private function sprite(?Specimen $specimen): ?string
    {
        if ($specimen === null) {
            return null;
        }

        return $specimen->is_shiny ? $specimen->species->sprite_shiny_path : $specimen->species->sprite_path;
    }
}
```

Se `RarityTier` não tiver `label()`, use o `TIER_META` equivalente em PHP: crie `label()` no enum (`Comum`, `Incomum`, `Raro`, `Lendário`) ou, se já existir outro nome, use ele. Se o PHPStan reclamar de `User` não usado, tire o `use`.

- [ ] **Step 6: Teste dos textos do `BuildNotice`** (no mesmo arquivo de teste)

```php
it('monta o aviso da proposta com o melhor espécime oferecido', function () {
    require_once __DIR__.'/../Trades/helpers.php';
    [$a, $b] = friends();
    $common = owned($a, ['is_shiny' => false]);
    $shiny = owned($a, ['is_shiny' => true]);
    $trade = propose($a, $b, [$common->id, $shiny->id], [owned($b)->id]);

    $notice = app(\App\Actions\Notify\BuildNotice::class)->tradeReceived($trade);

    expect($notice->kind)->toBe('trade_received')
        ->and($notice->dock)->toBe('trades')
        ->and($notice->title)->toBe("{$a->nickname} quer trocar")
        ->and($notice->isShiny)->toBeTrue()
        ->and($notice->href)->toBe('/trocas?aba=recebidas')
        ->and($notice->actorId)->toBe($a->id);
});
```

- [ ] **Step 7: Rodar** `php artisan test --compact tests/Feature/Actions/Notify/` → PASS; `composer ci:check` verde.

- [ ] **Step 8: Commit** `git add -A && git commit -m "Cria o aviso em tempo real e quem entrega ele depois do commit"`

### Task 3: Disparos nas trocas

**Files:**
- Modify: `app/Actions/Trades/ProposeTrade.php`, `app/Actions/Trades/AcceptTrade.php`, `app/Actions/Trades/DeclineTrade.php`
- Test: `tests/Feature/Actions/Trades/TradeNoticesTest.php`

**Interfaces:**
- Consumes: `Notifier::toPlayer(int, Notice)`, `BuildNotice::tradeReceived|tradeAccepted|tradeDeclined|tradeFallen(Trade)`.

- [ ] **Step 1: Teste (falha)**

```php
<?php

declare(strict_types=1);

use App\Actions\Trades\AcceptTrade;
use App\Actions\Trades\CancelTrade;
use App\Actions\Trades\CounterTrade;
use App\Actions\Trades\DeclineTrade;
use App\Events\PlayerNotified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake([PlayerNotified::class]));

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
    $c = \App\Models\User::factory()->create();
    \App\Models\Friendship::factory()->accepted()->create(['requester_id' => $c->id, 'addressee_id' => $b->id]);
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
    $gone->forceFill(['user_id' => \App\Models\User::factory()->create()->id])->save();

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
```

Confira as assinaturas reais de `CancelTrade::handle` e `CounterTrade::handle` antes (ajuste os argumentos se diferirem). Run → FAIL.

- [ ] **Step 2: `ProposeTrade`**: injete `Notifier` e `BuildNotice` no construtor (junto do que já existe) e, dentro da transação, logo antes de devolver a `Trade` criada:

```php
$this->notifier->toPlayer($trade->to_user_id, $this->notices->tradeReceived($trade));
```

(O `Notifier` espera o commit sozinho, então chamar dentro da transação é seguro.)

- [ ] **Step 3: `DeclineTrade`**: depois do `update` de recusa, dentro da transação:

```php
$this->notifier->toPlayer($locked->from_user_id, $this->notices->tradeDeclined($locked));
```

- [ ] **Step 4: `AcceptTrade`**: `cancelUnavailable` passa a avisar cada proposta que derruba. Troque o método por:

```php
/**
 * Derruba as propostas (espécime já não está com quem ia dar) e avisa
 * quem propôs cada uma.
 *
 * @param  Builder<Trade>  $query
 */
private function cancelUnavailable(Builder $query): void
{
    $fallen = $query->with('toUser.avatarSpecies')->get();

    Trade::query()->whereKey($fallen->modelKeys())->update([
        'status' => TradeStatus::Cancelled,
        'cancel_reason' => TradeCancelReason::Unavailable,
        'responded_at' => now(),
    ]);

    foreach ($fallen as $trade) {
        $this->notifier->toPlayer($trade->from_user_id, $this->notices->tradeFallen($trade));
    }
}
```

E, no caminho de sucesso, logo depois do `update(['status' => TradeStatus::Accepted, ...])`:

```php
$this->notifier->toPlayer($locked->from_user_id, $this->notices->tradeAccepted($locked));
```

Atenção: no caminho `'unavailable'` a transação **não** volta (ela retorna a string e só depois a exceção é lançada fora dela), então o aviso de `trade_fallen` sai. Não mude isso.

- [ ] **Step 5: Rodar** `php artisan test --compact tests/Feature/Actions/Trades/` → PASS (os testes antigos também); `composer ci:check` verde.

- [ ] **Step 6: Commit** `git add -A && git commit -m "Avisa em tempo real proposta, aceite, recusa e troca que caiu"`

### Task 4: Disparos na amizade e no feed

**Files:**
- Modify: `app/Actions/Friends/SendFriendRequest.php`, `app/Actions/Friends/AcceptFriendRequest.php`, `app/Actions/Pack/RevealSlot.php`
- Test: `tests/Feature/Actions/Notify/FriendAndFeedNoticesTest.php`

**Interfaces:**
- Consumes: `Notifier::toPlayer`, `Notifier::toGroup`, `BuildNotice::friendRequest|friendAccepted(Friendship)`, `BuildNotice::feedHighlight(Specimen)`.

- [ ] **Step 1: Teste (falha)**

```php
<?php

declare(strict_types=1);

use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Friends\BefriendInviter;
use App\Actions\Friends\SendFriendRequest;
use App\Actions\Pack\RevealSlot;
use App\Events\PlayerNotified;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake([PlayerNotified::class]));

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
```

Confira se `BefriendInviter` precisa de convite montado; se for simples, acrescente um teste que ele não dispara nada. Se a factory de `Species` usar outro nome de coluna/enum pro tier, ajuste. Run → FAIL.

- [ ] **Step 2: `SendFriendRequest`**: no `Friendship::create(...)` de pedido novo, guarde em `$friendship`, chame `$this->notifier->toPlayer($target->id, $this->notices->friendRequest($friendship))` e devolva. O caminho cruzado já passa por `AcceptFriendRequest` (que avisa, Step 3).

- [ ] **Step 3: `AcceptFriendRequest`**: depois do `update`, `$this->notifier->toPlayer($request->requester_id, $this->notices->friendAccepted($request));`. `BefriendInviter` **não** passa por aqui? Confira: se ele usar `AcceptFriendRequest`, passe um parâmetro `bool $notify = true` e chame com `false` no `BefriendInviter`.

- [ ] **Step 4: `RevealSlot`**: no bloco `if ($specimen->revealed_at === null)`, depois de gravar:

```php
$specimen->loadMissing('species', 'opening.user');

if ($specimen->is_shiny || in_array($specimen->species->rarity_tier->value, (array) config('game.group.feed_highlight_tiers'), true)) {
    $this->notifier->toGroup($this->notices->feedHighlight($specimen));
}
```

(injete `Notifier` e `BuildNotice` no construtor).

- [ ] **Step 5: Rodar** `php artisan test --compact` (suíte inteira) → PASS; `composer ci:check` verde.

- [ ] **Step 6: Commit** `git add -A && git commit -m "Avisa em tempo real amizade e o destaque do feed pro grupo"`

**Relatório do Kimi ao fim:** o que fez, decisões que tomou fora do plano, saída do `composer ci:check`.

---

## Parte do Claude (front), branch `notificacoes`

### Task 5: Echo e `useRealtime`

**Files:**
- Modify: `package.json` (`npm i laravel-echo pusher-js`)
- Create: `resources/js/lib/echo.ts`, `resources/js/composables/useRealtime.ts`, `resources/js/lib/notice.ts` (tipo `Notice` igual ao `toArray()` da Task 2)
- Modify: `resources/js/layouts/GroupLayout.vue`

- [ ] `echo.ts`: cria o `Echo` (broadcaster `reverb`, chaves `VITE_REVERB_*`, `forceTLS` pelo scheme, `enabledTransports: ['ws', 'wss']`) só no navegador, preguiçoso (`getEcho()`), com o CSRF pro `/broadcasting/auth`.
- [ ] `useRealtime(userId)`: assina `private('users.{id}')` e `private('group')`, ouve `.notice`, ignora `actorId === userId` no grupo, empurra numa fila reativa e chama `router.reload({ only })` com as props de `reload` que existem em `page.props`. Sai dos canais no unmount.
- [ ] `GroupLayout` monta o `useRealtime` com o id do usuário logado (prop compartilhada `auth.user.id`; conferir o nome).
- [ ] `vue-tsc` e `vp check` verdes; commit.

### Task 6: `DockBubble`, som e vibração

**Files:**
- Create: `resources/js/components/group/DockBubble.vue`
- Modify: `resources/js/components/entry/HomeDock.vue` (expõe a posição de cada ícone por `data-dock="trades|friends|home"` e o pulo), `resources/js/lib/pack/sfx.ts` (`notice(kind, tier, shiny)`), `resources/js/layouts/GroupLayout.vue`

- [ ] A bolha sai do ícone com mola, com o rabinho pro ícone e presa na tela. O ícone pula. Fica 4,5s; segurar pausa, arrastar pra baixo dispensa, tocar navega (`router.visit(href)`). Fila com "+N", e mais de 3 do mesmo `dock` viram "N novidades nas trocas/nos amigos/no feed".
- [ ] No `feed_highlight`, a moldura vem do `rarityLook(tier, isShiny)`. O sprite é o `FittedSprite`.
- [ ] `Sfx.notice`: plim de duas notas (sobe em `trade_accepted`, `friend_accepted` e `trade_received`; desce em `trade_declined` e `trade_fallen`; `chord(tier, shiny)` no `feed_highlight`), respeitando `isSoundMuted()`. O áudio só começa depois de um gesto do jogador: inicia no primeiro toque da página.
- [ ] Vibra 20ms em `trade_received` e `trade_accepted`. `role="status"` e `aria-live="polite"`; com movimento reduzido, só fade.
- [ ] Teste local com aviso falso (botão temporário só no dev, removido antes do commit), a 412px e no desktop.

### Task 7: Polls e junção

- [ ] Sai o `usePoll` de `trades/Index`. Home e Amigos ficam só com a presença (`only: ['friendsOnline']` / `['friends']`).
- [ ] Merge do branch do Kimi, revisão do código dele, `.env` local com o bloco `REVERB_*` (host `192.168.1.26`), `composer ci:check`.
- [ ] Teste com duas sessões no Playwright (Claude e uma segunda conta de teste): proposta → bolha do outro lado, aceite → bolha pra quem propôs, revelar shiny ou raro → bolha no grupo.
- [ ] SPEC (marca "Notificação via Reverb"; anota o Reverb em produção na seção Deploy), checkpoint no `CLAUDE.md`, merge no `main` e push.
