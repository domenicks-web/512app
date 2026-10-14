<?php

declare(strict_types=1);

use App\Actions\Admin\RevokeInvite;
use App\Exceptions\InviteNotRevocableException;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('revoga convite ativo e guarda quem revogou', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    $invite = Invite::factory()->create();

    (new RevokeInvite)->handle($admin, $invite);

    $invite->refresh();
    expect($invite->revoked_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($invite->revoked_by)->toBe($admin->id);
});

it('recusa revogar convite que não está ativo', function (string $state) {
    $invite = Invite::factory()->{$state}()->create();

    (new RevokeInvite)->handle(User::factory()->admin()->create(), $invite);
})->with(['revoked', 'exhausted', 'expired'])->throws(InviteNotRevocableException::class);
