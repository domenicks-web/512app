<?php

declare(strict_types=1);

use App\Models\Friendship;
use App\Models\Opening;
use App\Models\Sanction;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('derruba quem foi suspenso no próximo request e mostra o motivo no login', function () {
    $player = User::factory()->create();
    Sanction::factory()->for($player)->create(['reason' => 'spam']);

    $this->actingAs($player)->get('/')->assertRedirect('/login');

    $this->assertGuest();
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('flash.sanction', fn (string $message): bool => str_contains($message, 'Motivo: spam')));
});

it('recusa o login de suspenso, banido e da conta do sistema, mas não da suspensão vencida', function () {
    $suspended = User::factory()->create(['password' => 'senha-forte-1']);
    Sanction::factory()->for($suspended)->create();
    $banned = User::factory()->create(['password' => 'senha-forte-1']);
    Sanction::factory()->ban()->for($banned)->create(['reason' => 'trapaça']);
    $expired = User::factory()->create(['password' => 'senha-forte-1']);
    Sanction::factory()->for($expired)->create(['ends_at' => now()->subMinute()]);

    $this->postJson('/login', ['email' => $suspended->email, 'password' => 'senha-forte-1'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', fn (string $m): bool => str_starts_with($m, 'Sua conta está suspensa até'));
    $this->postJson('/login', ['email' => $banned->email, 'password' => 'senha-forte-1'])
        ->assertJsonPath('errors.email.0', 'Sua conta foi banida. Motivo: trapaça');
    $this->assertGuest();

    $this->postJson('/login', ['email' => $expired->email, 'password' => 'senha-forte-1'])->assertNoContent();
    $this->assertAuthenticatedAs($expired);
});

it('some do ranking, dos amigos, da busca, do cartão e do comparar enquanto suspenso', function () {
    [$viewer, $hidden] = User::factory()->count(2)->create();
    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => $hidden->id]);
    $mine = Specimen::factory()->for($viewer)->for(Opening::factory()->for($viewer))->create();
    $theirs = Specimen::factory()->for($hidden)->for(Opening::factory()->for($hidden))->create(['species_id' => $mine->species_id]);
    Sanction::factory()->for($hidden)->create();

    $this->actingAs($viewer)->get('/ranking')->assertInertia(fn (Assert $page) => $page
        ->where('rows', fn ($rows): bool => collect($rows)->pluck('userId')->doesntContain($hidden->id)));
    $this->actingAs($viewer)->get('/amigos')->assertInertia(fn (Assert $page) => $page->has('friends', 0));
    expect($viewer->friendIds())->toBe([]);
    $this->actingAs($viewer)->post('/amigos/pedidos', ['handle' => "{$hidden->nickname}#{$hidden->tag}"])
        ->assertSessionHasErrors(['handle' => 'Não achei ninguém com esse nickname#tag']);
    $this->actingAs($viewer)->get("/treinador/{$hidden->id}")->assertNotFound();
    $this->actingAs($viewer)->get("/comparar/{$mine->id}/{$theirs->id}")->assertNotFound();
});

it('nunca mostra a conta do sistema no ranking nem no admin', function () {
    $admin = User::factory()->admin()->create();
    $system = User::system();

    $this->actingAs($admin)->get('/ranking')->assertInertia(fn (Assert $page) => $page
        ->where('rows', fn ($rows): bool => collect($rows)->pluck('userId')->doesntContain($system->id)));
    $this->actingAs($admin)->get('/admin/jogadores')->assertInertia(fn (Assert $page) => $page
        ->where('players', fn ($players): bool => collect($players)->pluck('id')->doesntContain($system->id)));
});
