<?php

declare(strict_types=1);

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('admin pages', [
    'convites' => '/admin/convites',
    'usuários' => '/admin/usuarios',
]);

it('manda deslogado pro login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with('admin pages');

it('barra jogador comum com 403', function (string $url) {
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

it('barra jogador comum em todas as ações de escrita', function () {
    $player = User::factory()->create();
    $other = User::factory()->create();
    $invite = Invite::factory()->create();

    $this->actingAs($player)->post('/admin/convites', ['max_uses' => 1])->assertForbidden();
    $this->actingAs($player)->post("/admin/convites/{$invite->id}/revogar")->assertForbidden();
    $this->actingAs($player)->post("/admin/usuarios/{$other->id}/admin")->assertForbidden();
    $this->actingAs($player)->post("/admin/usuarios/{$player->id}/admin")->assertForbidden();
    $this->actingAs($player)->delete("/admin/usuarios/{$other->id}/admin")->assertForbidden();

    expect(Invite::count())->toBe(1)
        ->and($invite->fresh()->revoked_at)->toBeNull()
        ->and($player->fresh()->is_admin)->toBeFalse()
        ->and($other->fresh()->is_admin)->toBeFalse();
});
