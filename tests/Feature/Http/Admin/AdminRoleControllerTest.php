<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lista os usuários com admins primeiro', function () {
    $player = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/usuarios')->assertInertia(fn (Assert $page) => $page
        ->component('admin/Users')
        ->has('users', 2)
        ->where('users.0.id', $admin->id)
        ->where('users.0.is_admin', true)
        ->where('users.1.id', $player->id)
        ->where('users.1.is_admin', false)
    );
});

it('promove outro usuário a admin', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)->post("/admin/usuarios/{$player->id}/admin")->assertRedirect(route('admin.users.index'));

    expect($player->fresh()->is_admin)->toBeTrue();
});

it('o admin promovido passa a acessar o painel', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)->post("/admin/usuarios/{$player->id}/admin");

    $this->actingAs($player->fresh())->get('/admin/convites')->assertOk();
});

it('remove o admin de outro admin', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    $this->actingAs($admin)->delete("/admin/usuarios/{$other->id}/admin")->assertRedirect(route('admin.users.index'));

    expect($other->fresh()->is_admin)->toBeFalse();
    $this->actingAs($other->fresh())->get('/admin/convites')->assertForbidden();
});

it('não deixa o admin remover o próprio acesso', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from('/admin/usuarios')
        ->delete("/admin/usuarios/{$admin->id}/admin")
        ->assertRedirect('/admin/usuarios')
        ->assertSessionHasErrors('admin');

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('404 pra usuário inexistente', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/usuarios/999999/admin')->assertNotFound();
});
