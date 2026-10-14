<?php

declare(strict_types=1);

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('admin pages', [
    'convites' => '/admin/convites',
    'jogadores' => '/admin/jogadores',
]);

it('manda visitante pro login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with('admin pages');

it('barra jogador comum', function (string $url) {
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with('admin pages');

it('deixa admin entrar', function (string $url) {
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
})->with('admin pages');

it('manda admin sem perfil completo pro onboarding antes', function () {
    $admin = User::factory()->admin()->withoutProfile()->create();

    $this->actingAs($admin)->get('/admin/convites')->assertRedirect(route('onboarding.show'));
});

it('redireciona /admin pra lista de convites', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertRedirect('/admin/convites');
});

it('barra jogador comum em todas as ações', function () {
    $player = User::factory()->create();
    $other = User::factory()->create();
    $invite = Invite::factory()->create();

    $this->actingAs($player)->get('/admin/convites/sufixo')->assertForbidden();
    $this->actingAs($player)->post('/admin/convites', ['prefix' => 'X', 'max_uses' => 1, 'validity' => '7'])->assertForbidden();
    $this->actingAs($player)->post("/admin/convites/{$invite->id}/revogar")->assertForbidden();
    $this->actingAs($player)->patch("/admin/jogadores/{$other->id}/cota", ['quota' => 5])->assertForbidden();
    $this->actingAs($player)->patch("/admin/jogadores/{$player->id}/papel", ['is_admin' => true, 'confirm' => $player->nicknameWithTag()])->assertForbidden();
    $this->actingAs($player)->post("/admin/jogadores/{$other->id}/liberar-nascimento")->assertForbidden();
    $this->actingAs($player)->post("/admin/jogadores/{$other->id}/reenviar-confirmacao")->assertForbidden();
    $this->actingAs($player)->put("/admin/jogadores/{$other->id}/senha", ['password' => 'x1234567', 'password_confirmation' => 'x1234567'])->assertForbidden();

    expect(Invite::count())->toBe(1)
        ->and($invite->fresh()->revoked_at)->toBeNull()
        ->and($player->fresh()->is_admin)->toBeFalse()
        ->and($other->fresh()->invite_quota)->toBe(config('game.invites.default_quota'));
});
