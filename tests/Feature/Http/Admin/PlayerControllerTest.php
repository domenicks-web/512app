<?php

declare(strict_types=1);

use App\Models\Invite;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lista jogadores com quem convidou, admins primeiro', function () {
    $admin = User::factory()->admin()->create(['nickname' => 'Ramon']);
    $invite = Invite::factory()->create(['created_by' => $admin->id, 'code' => 'RAMON-7X2K']);
    User::factory()->create(['nickname' => 'Lin', 'tag' => '4821', 'invited_by' => $invite->id]);

    $this->actingAs($admin)->get('/admin/jogadores')->assertInertia(fn (Assert $page) => $page
        ->component('admin/Players')
        ->has('players', 2)
        ->where('players.0.nickname', 'Ramon')
        ->where('players.0.invite_code', null)
        ->where('players.1.handle', 'Lin#4821')
        ->where('players.1.invite_code', 'RAMON-7X2K')
        ->where('players.1.invited_by', 'Ramon')
        ->where('players.1.specimens_count', 0)
        ->where('quota', ['min' => 0, 'max' => 9])
    );
});

it('busca por nickname, Nick#tag e email', function (string $search, array $expected) {
    $admin = User::factory()->admin()->create(['nickname' => 'Ramon', 'tag' => '0001', 'email' => 'ramon@512app.com']);
    User::factory()->create(['nickname' => 'Lin', 'tag' => '4821', 'email' => 'lin.yamada@gmail.com']);
    User::factory()->create(['nickname' => 'Lin', 'tag' => '0772', 'email' => 'lindsey@icloud.com']);

    $this->actingAs($admin)->get('/admin/jogadores?q='.urlencode($search))->assertInertia(fn (Assert $page) => $page
        ->where('search', $search)
        ->where('players', fn ($players) => collect($players)->pluck('handle')->sort()->values()->all() === $expected)
    );
})->with([
    'nickname' => ['lin', ['Lin#0772', 'Lin#4821']],
    'nick com tag' => ['Lin#48', ['Lin#4821']],
    'email' => ['icloud', ['Lin#0772']],
    'nada' => ['zzz', []],
]);

it('muda a cota dentro da faixa e recusa admin', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)->patch("/admin/jogadores/{$player->id}/cota", ['quota' => 9])->assertSessionHasNoErrors();
    expect($player->fresh()->invite_quota)->toBe(9);

    $this->patch("/admin/jogadores/{$player->id}/cota", ['quota' => 10])->assertSessionHasErrors('quota');
    $this->patch("/admin/jogadores/{$admin->id}/cota", ['quota' => 3])->assertSessionHasErrors(['quota' => 'Admin gera convites sem cota.']);
});

it('promove e rebaixa com a confirmação digitada', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create(['nickname' => 'Lin', 'tag' => '4821']);

    $this->actingAs($admin)->patch("/admin/jogadores/{$player->id}/papel", ['is_admin' => true, 'confirm' => '  lin#4821 '])
        ->assertSessionHasNoErrors();
    expect($player->fresh()->is_admin)->toBeTrue();

    $this->patch("/admin/jogadores/{$player->id}/papel", ['is_admin' => false, 'confirm' => 'Lin#4821'])
        ->assertSessionHasNoErrors();
    expect($player->fresh()->is_admin)->toBeFalse();
});

it('recusa a confirmação errada', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create(['nickname' => 'Lin', 'tag' => '4821']);

    $this->actingAs($admin)->patch("/admin/jogadores/{$player->id}/papel", ['is_admin' => true, 'confirm' => 'Lin'])
        ->assertSessionHasErrors(['confirm' => 'Digite Lin#4821 pra confirmar.']);

    expect($player->fresh()->is_admin)->toBeFalse();
});

it('dá 403 ao mexer no próprio papel e 422 ao remover o último admin', function () {
    $admin = User::factory()->admin()->create(['nickname' => 'Ramon', 'tag' => '0001']);

    $this->actingAs($admin)->patch("/admin/jogadores/{$admin->id}/papel", ['is_admin' => false, 'confirm' => 'Ramon#0001'])
        ->assertForbidden();

    $formerAdmin = User::factory()->create();
    $this->actingAs($formerAdmin)->patch("/admin/jogadores/{$admin->id}/papel", ['is_admin' => false, 'confirm' => 'Ramon#0001'])
        ->assertForbidden(); // jogador comum nem passa do Gate

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('libera outra correção da data de nascimento', function () {
    $player = User::factory()->create(['birthdate_corrected_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())->post("/admin/jogadores/{$player->id}/liberar-nascimento")->assertRedirect();

    expect($player->fresh()->birthdate_corrected_at)->toBeNull();
});

it('reenvia a confirmação de email e avisa quando já foi confirmado', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($admin)->post("/admin/jogadores/{$unverified->id}/reenviar-confirmacao")->assertSessionHasNoErrors();
    Notification::assertSentTo($unverified, VerifyEmail::class);

    $this->post("/admin/jogadores/{$admin->id}/reenviar-confirmacao")->assertSessionHasErrors('verification');
});

it('troca a senha de outro jogador, mas não a própria', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)->put("/admin/jogadores/{$player->id}/senha", ['password' => 'senha-nova-1', 'password_confirmation' => 'senha-nova-1'])
        ->assertSessionHasNoErrors();
    expect(Hash::check('senha-nova-1', $player->fresh()->password))->toBeTrue();

    $this->put("/admin/jogadores/{$player->id}/senha", ['password' => 'a', 'password_confirmation' => 'b'])->assertSessionHasErrors('password');
    $this->put("/admin/jogadores/{$admin->id}/senha", ['password' => 'senha-nova-1', 'password_confirmation' => 'senha-nova-1'])->assertForbidden();
});
