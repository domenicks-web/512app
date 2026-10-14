<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Actions/Trades/helpers.php';

uses(RefreshDatabase::class);

it('monta a mesa com as coleções dos dois e marca os repetidos', function () {
    [$a, $b] = friends();
    $one = owned($a);
    owned($a, ['species_id' => $one->species_id]);
    $wanted = owned($b);

    $this->actingAs($a)->get("/trocas/nova/{$b->id}?pedir={$wanted->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('trades/Create')
            ->where('friend.id', $b->id)
            ->has('mine', 2)
            ->where('mine.0.duplicate', true)
            ->has('theirs', 1)
            ->where('initial.receive', [$wanted->id])
            ->where('initial.give', [])
            ->where('counterOf', null)
            ->where('limits.perSide', 3));
});

it('dá 404 pra quem não é amigo', function () {
    [$a] = friends();

    $this->actingAs($a)->get('/trocas/nova/'.User::factory()->create()->id)->assertNotFound();
});

it('ignora ?pedir= de espécime que não é do amigo', function () {
    [$a, $b] = friends();
    $mine = owned($a);

    $this->actingAs($a)->get("/trocas/nova/{$b->id}?pedir={$mine->id}")
        ->assertInertia(fn (Assert $page) => $page->where('initial.receive', []));
});

it('contraproposta abre a mesa invertida pro destinatário', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $theirs = owned($b);
    $trade = propose($a, $b, [$mine->id], [$theirs->id]);

    $this->actingAs($b)->get("/trocas/{$trade->id}/contrapropor")
        ->assertInertia(fn (Assert $page) => $page
            ->where('friend.id', $a->id)
            ->where('initial.give', [$theirs->id])
            ->where('initial.receive', [$mine->id])
            ->where('counterOf', $trade->id));

    $this->actingAs($a)->get("/trocas/{$trade->id}/contrapropor")->assertNotFound();
});
