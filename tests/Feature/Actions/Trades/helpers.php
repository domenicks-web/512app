<?php

declare(strict_types=1);

use App\Actions\Trades\ProposeTrade;
use App\Models\Friendship;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\User;

function owned(User $owner, array $attributes = []): Specimen
{
    return Specimen::factory()->for($owner)->for(Opening::factory()->for($owner))->create($attributes);
}

function friends(): array
{
    // O avatar de fallback (1º do config) precisa existir: o aceite chama
    // ResetUnownedAvatar, que grava esse id nos dois lados.
    $fallback = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$fallback->id]]);

    [$a, $b] = User::factory()->count(2)->create();
    Friendship::factory()->accepted()->create(['requester_id' => $a->id, 'addressee_id' => $b->id]);

    return [$a, $b];
}

function propose(User $from, User $to, array $offered, array $requested, ?string $message = null): Trade
{
    return app(ProposeTrade::class)->handle($from, $to, $offered, $requested, $message);
}
