<?php

declare(strict_types=1);

use App\Models\Sanction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('considera ativa a suspensão no prazo e o banimento, e não a vencida nem a encerrada', function () {
    $running = Sanction::factory()->create();
    $expired = Sanction::factory()->create(['ends_at' => now()->subMinute()]);
    $lifted = Sanction::factory()->create(['lifted_at' => now()]);
    $ban = Sanction::factory()->ban()->create();

    expect(Sanction::query()->active()->pluck('id')->all())
        ->toEqualCanonicalizing([$running->id, $ban->id])
        ->and($expired->user->activeSanction())->toBeNull()
        ->and($lifted->user->activeSanction())->toBeNull();
});

it('deixa de fora do grupo o sistema, o suspenso e o banido', function () {
    $free = User::factory()->create();
    $expired = User::factory()->create();
    Sanction::factory()->for($expired)->create(['ends_at' => now()->subDay()]);
    Sanction::factory()->for($suspended = User::factory()->create())->create();
    Sanction::factory()->ban()->for($banned = User::factory()->create())->create();

    $active = User::query()->active()->pluck('id')->all();

    expect($active)->toContain($free->id, $expired->id)
        ->not->toContain($suspended->id, $banned->id, User::system()->id);
});

it('explica a sanção no texto do login', function () {
    $suspension = Sanction::factory()->create(['reason' => 'spam', 'ends_at' => Carbon::create(2026, 10, 18, 14, 0, 0, 'America/Sao_Paulo')->utc()]);
    $ban = Sanction::factory()->ban()->create(['reason' => 'trapaça']);

    expect($suspension->message())->toBe('Sua conta está suspensa até 18/10 às 14:00. Motivo: spam')
        ->and($ban->message())->toBe('Sua conta foi banida. Motivo: trapaça');
});
