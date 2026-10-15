<?php

declare(strict_types=1);

use App\Actions\Admin\BanPlayer;
use App\Actions\Admin\LiftSuspension;
use App\Actions\Admin\SuspendPlayer;
use App\Enums\SanctionType;
use App\Enums\TradeStatus;
use App\Events\PlayerNotified;
use App\Exceptions\SanctionNotAllowedException;
use App\Mail\SanctionedMail;
use App\Models\Friendship;
use App\Models\Opening;
use App\Models\Specimen;
use App\Models\SpecimenTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/../Trades/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Event::fake([PlayerNotified::class]);
});

function suspend(?User $admin, User $target, int $days = 3): void
{
    app(SuspendPlayer::class)->handle($admin, $target, $days, 'Spam no grupo');
}

it('suspende: derruba as sessões, troca o remember token, cancela as trocas e manda email', function () {
    [$a, $b] = friends();
    $admin = User::factory()->admin()->create();
    $toHim = propose($a, $b, [owned($a)->id], [owned($b)->id]);
    $fromHim = propose($b, $a, [owned($b)->id], [owned($a)->id]);
    DB::table('sessions')->insert(['id' => 'x', 'user_id' => $b->id, 'payload' => '', 'last_activity' => time()]);
    $token = $b->remember_token;

    suspend($admin, $b);

    expect($b->fresh()->activeSanction()?->type)->toBe(SanctionType::Suspension)
        ->and(DB::table('sessions')->where('user_id', $b->id)->exists())->toBeFalse()
        ->and($b->fresh()->remember_token)->not->toBe($token)
        ->and($toHim->fresh()->status)->toBe(TradeStatus::Cancelled)
        ->and($fromHim->fresh()->status)->toBe(TradeStatus::Cancelled);
    Mail::assertSent(SanctionedMail::class, fn (SanctionedMail $mail): bool => $mail->hasTo($b->email));
    Event::assertDispatched(PlayerNotified::class, fn (PlayerNotified $e): bool => $e->channel === "users.{$a->id}" && $e->notice->kind === 'trade_fallen');
    Event::assertNotDispatched(PlayerNotified::class, fn (PlayerNotified $e): bool => $e->notice->kind === 'trade_fallen' && $e->channel === "users.{$b->id}");
});

it('recusa prazo fora do config, admin, a si mesmo, o sistema e quem já está suspenso', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    expect(fn () => suspend($admin, $player, 2))->toThrow(SanctionNotAllowedException::class, 'Prazo inválido.')
        ->and(fn () => suspend($admin, User::factory()->admin()->create()))->toThrow(SanctionNotAllowedException::class, 'Tire o papel de admin antes.')
        ->and(fn () => suspend($player, $player))->toThrow(SanctionNotAllowedException::class, 'Você não pode se punir.')
        ->and(fn () => suspend($admin, User::system()))->toThrow(SanctionNotAllowedException::class, 'Essa conta é do sistema.');

    suspend($admin, $player);

    expect(fn () => suspend($admin, $player))->toThrow(SanctionNotAllowedException::class, 'Esse jogador já está suspenso ou banido.');
});

it('bane: liberta tudo pro sistema com o mesmo mint, apaga amizades e encerra a suspensão', function () {
    [$a, $b] = friends();
    $admin = User::factory()->admin()->create();
    $revealed = owned($b, ['mint_number' => 1]);
    $closed = Specimen::factory()->unrevealed()->for($b)->for(Opening::factory()->for($b))->create();
    suspend($admin, $b);

    app(BanPlayer::class)->handle($admin, $b, 'Trapaça');

    $system = User::system();
    $suspension = $b->sanctions()->where('type', SanctionType::Suspension)->first();

    expect($b->fresh()->activeSanction()?->type)->toBe(SanctionType::Ban)
        ->and($suspension->lifted_at)->not->toBeNull()
        ->and($revealed->fresh()->user_id)->toBe($system->id)
        ->and($revealed->fresh()->mint_number)->toBe(1)
        ->and(Specimen::query()->withoutGlobalScopes()->find($closed->id)->user_id)->toBe($system->id)
        ->and(SpecimenTransfer::query()->where('specimen_id', $revealed->id)->whereNull('trade_id')->where('to_user_id', $system->id)->exists())->toBeTrue()
        ->and(Friendship::query()->involving($b)->exists())->toBeFalse();
    expect(fn () => app(BanPlayer::class)->handle($admin, $b, 'De novo'))->toThrow(SanctionNotAllowedException::class);
});

it('na libertação do banido o apelido vai pro histórico e o espécime fica sem', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    $specimen = owned($player, ['nickname' => 'João']);

    app(BanPlayer::class)->handle($admin, $player, 'Trapaça');

    $system = User::system();

    expect($specimen->fresh()->user_id)->toBe($system->id)
        ->and($specimen->fresh()->nickname)->toBeNull()
        ->and(SpecimenTransfer::query()->where('specimen_id', $specimen->id)->value('nickname'))->toBe('João');
});

it('encerra a suspensão antes do prazo, mas não o banimento', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    suspend($admin, $player);

    app(LiftSuspension::class)->handle($admin, $player);

    expect($player->fresh()->activeSanction())->toBeNull();

    app(BanPlayer::class)->handle($admin, $player, 'Trapaça');

    expect(fn () => app(LiftSuspension::class)->handle($admin, $player))->toThrow(SanctionNotAllowedException::class, 'Esse jogador não está suspenso.');
});
