<?php

declare(strict_types=1);

use App\Enums\SanctionType;
use App\Models\Opening;
use App\Models\Sanction;
use App\Models\Specimen;
use App\Models\SpecimenTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

it('só admin suspende', function () {
    $player = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post("/admin/jogadores/{$player->id}/suspender", ['days' => 3, 'reason' => 'Spam no grupo'])
        ->assertForbidden();
});

it('suspende, bane e encerra pelo painel, com toast', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)->post("/admin/jogadores/{$player->id}/suspender", ['days' => 3, 'reason' => 'Spam no grupo'])
        ->assertRedirect()->assertInertiaFlash('toast', "{$player->nickname} suspenso por 3 dias");
    expect($player->activeSanction()?->type)->toBe(SanctionType::Suspension);

    $this->actingAs($admin)->post("/admin/jogadores/{$player->id}/encerrar-suspensao")->assertRedirect();
    expect($player->activeSanction())->toBeNull();

    $this->actingAs($admin)->post("/admin/jogadores/{$player->id}/banir", ['reason' => 'Trapaça', 'confirm' => "{$player->nickname}#{$player->tag}"])
        ->assertRedirect()->assertInertiaFlash('toast', "{$player->nickname} banido");
    expect($player->activeSanction()?->type)->toBe(SanctionType::Ban);
});

it('valida prazo, motivo e a confirmação do banimento', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)->post("/admin/jogadores/{$player->id}/suspender", ['days' => 2, 'reason' => 'oi'])
        ->assertSessionHasErrors(['days' => 'Prazo inválido.', 'reason' => 'Escreva um motivo com pelo menos 5 letras.']);
    $this->actingAs($admin)->post("/admin/jogadores/{$player->id}/banir", ['reason' => 'Trapaça', 'confirm' => 'outro#0000'])
        ->assertSessionHasErrors('confirm');
});

it('volta com o erro da regra quando não pode punir', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    $this->actingAs($admin)->post("/admin/jogadores/{$other->id}/suspender", ['days' => 3, 'reason' => 'Spam no grupo'])
        ->assertSessionHasErrors(['sanction' => 'Tire o papel de admin antes.']);
});

it('lista a sanção ativa e os prazos no painel', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    Sanction::factory()->for($player)->create(['reason' => 'Spam no grupo']);

    $this->actingAs($admin)->get('/admin/jogadores')->assertInertia(fn (Assert $page) => $page
        ->where('sanctionDays', [1, 3, 7, 30])
        ->where('players', fn ($players): bool => collect($players)->firstWhere('id', $player->id)['sanction']['reason'] === 'Spam no grupo'));
});

it('mostra a libertação no histórico da ficha', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    $specimen = Specimen::factory()->for($player)->for(Opening::factory()->for($player))->create();
    $this->actingAs($admin)->post("/admin/jogadores/{$player->id}/banir", ['reason' => 'Trapaça', 'confirm' => "{$player->nickname}#{$player->tag}"]);

    expect(SpecimenTransfer::query()->where('specimen_id', $specimen->id)->value('trade_id'))->toBeNull();
    $this->actingAs($admin)->getJson("/especime/{$specimen->id}")
        ->assertJsonPath('specimens.0.history.1.kind', 'released');
});

it('suspende, bane e encerra pelos comandos', function () {
    $player = User::factory()->create();

    $this->artisan('admin:suspend', ['player' => $player->email, 'days' => 7, 'reason' => 'Spam no grupo'])->assertSuccessful();
    $this->artisan('admin:suspend', ['player' => $player->email, 'days' => 7, 'reason' => 'De novo'])->assertFailed();
    $this->artisan('admin:lift', ['player' => $player->email])->assertSuccessful();
    $this->artisan('admin:ban', ['player' => $player->email, 'reason' => 'Trapaça', '--force' => true])->assertSuccessful();

    expect($player->activeSanction()?->type)->toBe(SanctionType::Ban);
});
