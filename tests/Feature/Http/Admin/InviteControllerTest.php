<?php

declare(strict_types=1);

use App\Http\Requests\Admin\StoreInviteRequest;
use App\Models\Invite;
use App\Models\InviteRedemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lista os convites com status, criador, quem usou e o padrão do gerador', function () {
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
        ->where('invites.1.status', 'active')
        ->where('invites.1.created_by', 'Ramon')
        ->where('invites.1.redemptions.0.handle', 'Lin#0042')
        ->where('counts.invites', 1)
        ->where('counts.players', 2)
        ->where('form.defaultPrefix', 'RAMON')
        ->where('form.validities', ['7', '30', 'never'])
    );
});

it('sorteia o sufixo da prévia no servidor e grava esse mesmo no POST', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();

    $suffix = $this->actingAs($admin)->getJson('/admin/convites/sufixo')
        ->assertOk()
        ->assertSessionHas(StoreInviteRequest::SUFFIX_SESSION_KEY)
        ->json('suffix');

    $this->post('/admin/convites', ['prefix' => 'festa', 'max_uses' => 3, 'validity' => '7'])
        ->assertRedirect()
        ->assertSessionMissing(StoreInviteRequest::SUFFIX_SESSION_KEY);

    $invite = Invite::sole();
    expect($invite->code)->toBe("FESTA-{$suffix}")
        ->and($invite->max_uses)->toBe(3)
        ->and($invite->expires_at->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString());
});

it('cria convite sem validade', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/admin/convites', ['prefix' => 'RAMON', 'max_uses' => 1, 'validity' => 'never']);

    expect(Invite::sole()->expires_at)->toBeNull();
});

it('valida prefixo, usos e validade', function (array $payload, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/admin/convites', array_merge(['prefix' => 'RAMON', 'max_uses' => 1, 'validity' => '30'], $payload))
        ->assertSessionHasErrors($field);

    expect(Invite::count())->toBe(0);
})->with([
    'prefixo com hífen' => [['prefix' => 'RA-MON'], 'prefix'],
    'prefixo longo' => [['prefix' => 'ABCDEFGHI'], 'prefix'],
    'prefixo vazio' => [['prefix' => ''], 'prefix'],
    'zero usos' => [['max_uses' => 0], 'max_uses'],
    'onze usos' => [['max_uses' => 11], 'max_uses'],
    'validade inventada' => [['validity' => '90'], 'validity'],
]);

it('revoga convite ativo e recusa revogar de novo', function () {
    $admin = User::factory()->admin()->create();
    $invite = Invite::factory()->create();

    $this->actingAs($admin)->post("/admin/convites/{$invite->id}/revogar")->assertRedirect()->assertSessionHasNoErrors();
    expect($invite->fresh()->revoked_by)->toBe($admin->id);

    $this->post("/admin/convites/{$invite->id}/revogar")->assertSessionHasErrors(['invite' => 'Esse convite já foi revogado.']);
});
