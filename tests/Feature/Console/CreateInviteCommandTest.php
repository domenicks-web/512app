<?php

declare(strict_types=1);

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria convite de 1 uso assinado pelo primeiro admin por padrão', function () {
    $admin = User::factory()->admin()->create(['nickname' => 'Ramon']);

    $this->artisan('invites:create')->assertSuccessful();

    $invite = Invite::sole();
    expect($invite->created_by)->toBe($admin->id)
        ->and($invite->max_uses)->toBe(1)
        ->and($invite->expires_at)->toBeNull()
        ->and($invite->code)->toStartWith('RAMON-');
});

it('aceita admin específico, infinito e validade', function () {
    User::factory()->admin()->create();
    $chosen = User::factory()->admin()->create(['email' => 'outro@example.com']);

    $this->artisan('invites:create', ['--by' => 'outro@example.com', '--unlimited' => true, '--expires-in-days' => 3])
        ->assertSuccessful();

    $invite = Invite::sole();
    expect($invite->created_by)->toBe($chosen->id)
        ->and($invite->max_uses)->toBeNull()
        ->and($invite->expires_at)->not->toBeNull();
});

it('falha sem admin', function () {
    User::factory()->create(['email' => 'comum@example.com']);

    $this->artisan('invites:create')->assertFailed();
    $this->artisan('invites:create', ['--by' => 'comum@example.com'])->assertFailed();

    expect(Invite::count())->toBe(0);
});

it('falha com número inválido', function () {
    User::factory()->admin()->create();

    $this->artisan('invites:create', ['--max-uses' => '0'])->assertFailed();
    $this->artisan('invites:create', ['--expires-in-days' => 'abc'])->assertFailed();

    expect(Invite::count())->toBe(0);
});
