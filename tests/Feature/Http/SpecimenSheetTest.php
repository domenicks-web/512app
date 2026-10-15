<?php

declare(strict_types=1);

use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve a ficha de um espécime de qualquer jogador, com o histórico', function () {
    $specimen = Specimen::factory()->create();

    $this->actingAs(User::factory()->create())->getJson("/especime/{$specimen->id}")
        ->assertOk()
        ->assertJsonCount(1, 'specimens')
        ->assertJsonPath('specimens.0.id', $specimen->id)
        ->assertJsonPath('specimens.0.history.0.kind', 'caught');
});

it('não mostra espécime de bola fechada', function () {
    $specimen = Specimen::factory()->unrevealed()->create();

    $this->actingAs(User::factory()->create())->getJson("/especime/{$specimen->id}")->assertNotFound();
});

it('pede login', function () {
    $this->getJson('/especime/'.Specimen::factory()->create()->id)->assertUnauthorized();
});
