<?php

declare(strict_types=1);

use App\Models\Invite;
use App\Models\InviteRedemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lista os convites com status, criador e quem usou', function () {
    $admin = User::factory()->admin()->create(['nickname' => 'Ramon']);
    $invite = Invite::factory()->create(['created_by' => $admin->id, 'code' => 'RAMON-AAAA', 'max_uses' => 2, 'uses_count' => 1]);
    $friend = User::factory()->create(['nickname' => 'Lin', 'tag' => '0042']);
    InviteRedemption::create(['invite_id' => $invite->id, 'user_id' => $friend->id]);
    Invite::factory()->revoked()->create(['created_by' => $admin->id, 'code' => 'RAMON-BBBB']);

    $this->actingAs($admin)->get('/admin/convites')->assertInertia(fn (Assert $page) => $page
        ->component('admin/Invites')
        ->has('invites', 2)
        ->where('invites.0.code', 'RAMON-BBBB')
        ->where('invites.0.status', 'revoked')
        ->where('invites.1.code', 'RAMON-AAAA')
        ->where('invites.1.status', 'active')
        ->where('invites.1.created_by', 'Ramon')
        ->where('invites.1.redemptions.0.user', 'Lin#0042')
    );
});

it('cria convite com limite e validade', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create(['nickname' => 'Ramon']);

    $response = $this->actingAs($admin)->post('/admin/convites', ['max_uses' => 3, 'expires_in_days' => 7]);

    $invite = Invite::sole();
    $response->assertRedirect(route('admin.invites.index'))
        ->assertSessionHas('created_invite_code', $invite->code);

    expect($invite->code)->toStartWith('RAMON-')
        ->and($invite->created_by)->toBe($admin->id)
        ->and($invite->max_uses)->toBe(3)
        ->and($invite->expires_at->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString());
});

it('cria convite infinito e sem validade quando os campos vêm vazios', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/convites', ['max_uses' => '', 'expires_in_days' => ''])->assertSessionHasNoErrors();

    $invite = Invite::sole();
    expect($invite->max_uses)->toBeNull()
        ->and($invite->expires_at)->toBeNull();
});

it('recusa limite inválido', function (array $payload, string $field) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/convites', $payload)->assertSessionHasErrors($field);

    expect(Invite::count())->toBe(0);
})->with([
    'zero usos' => [['max_uses' => 0], 'max_uses'],
    'usos negativos' => [['max_uses' => -1], 'max_uses'],
    'usos acima do teto' => [['max_uses' => 1001], 'max_uses'],
    'texto em usos' => [['max_uses' => 'abc'], 'max_uses'],
    'zero dias' => [['expires_in_days' => 0], 'expires_in_days'],
    'dias acima do teto' => [['expires_in_days' => 366], 'expires_in_days'],
]);

it('revoga o convite e o cadastro passa a recusar o código', function () {
    $admin = User::factory()->admin()->create();
    $invite = Invite::factory()->create(['code' => 'RAMON-CCCC', 'max_uses' => null]);

    $this->actingAs($admin)->post("/admin/convites/{$invite->id}/revogar")->assertRedirect(route('admin.invites.index'));

    expect($invite->fresh()->isRevoked())->toBeTrue();

    auth()->logout();

    $this->post('/cadastro', [
        'email' => 'novo@example.com',
        'password' => 'segredo-forte-123',
        'password_confirmation' => 'segredo-forte-123',
        'invite_code' => 'RAMON-CCCC',
    ])->assertSessionHasErrors('invite_code');

    expect(User::where('email', 'novo@example.com')->exists())->toBeFalse();
});

it('não muda a data quando revoga um convite já revogado', function () {
    $admin = User::factory()->admin()->create();
    $invite = Invite::factory()->revoked()->create();
    $originalRevokedAt = $invite->revoked_at;

    $this->actingAs($admin)->post("/admin/convites/{$invite->id}/revogar");

    expect($invite->fresh()->revoked_at->equalTo($originalRevokedAt))->toBeTrue();
});

it('um convite criado pelo painel funciona no cadastro de ponta a ponta', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post('/admin/convites', ['max_uses' => 1]);
    $code = Invite::sole()->code;
    auth()->logout();

    $this->postJson('/cadastro/convite', ['invite_code' => $code])->assertOk();
    $this->postJson('/cadastro', [
        'email' => 'amigo@example.com',
        'password' => 'segredo-forte-123',
        'password_confirmation' => 'segredo-forte-123',
    ])->assertNoContent();

    expect(Invite::sole()->uses_count)->toBe(1)
        ->and(Invite::sole()->isRedeemable())->toBeFalse();
});
