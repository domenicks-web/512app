<?php

declare(strict_types=1);

use App\Actions\Trades\AcceptTrade;
use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Actions/Trades/helpers.php';

uses(RefreshDatabase::class);

it('mostra na ficha a captura e cada troca, em ordem', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $trade = propose($a, $b, [$mine->id], [owned($b)->id]);
    app(AcceptTrade::class)->handle($trade, $b);

    $this->actingAs($b)->getJson("/colecao/{$mine->species_id}")
        ->assertJsonPath('specimens.0.history.0.kind', 'caught')
        ->assertJsonPath('specimens.0.history.0.nickname', $a->nickname)
        ->assertJsonPath('specimens.0.history.1.kind', 'traded')
        ->assertJsonPath('specimens.0.history.1.nickname', $b->nickname)
        ->assertJsonPath('specimens.0.history.1.fromNickname', $a->nickname);
});

it('marca na coleção a espécie que veio de troca', function () {
    [$a, $b] = friends();
    $mine = owned($a);
    $trade = propose($a, $b, [$mine->id], [owned($b)->id]);
    app(AcceptTrade::class)->handle($trade, $b);

    $this->actingAs($b)->get('/colecao')
        ->assertInertia(fn (Assert $page) => $page->where(
            'species',
            fn ($species) => collect($species)->firstWhere('id', $mine->species_id)['fromTrade'] === true,
        ));
});

it('põe no feed só a troca com shiny ou tier de destaque', function () {
    [$a, $b] = friends();
    $rare = Species::factory()->create(['rarity_tier' => 'raro']);
    $common = Species::factory()->create(['rarity_tier' => 'comum']);
    $good = propose($a, $b, [owned($a, ['species_id' => $rare->id])->id], [owned($b, ['species_id' => $common->id])->id]);
    $boring = propose($a, $b, [owned($a, ['species_id' => $common->id])->id], [owned($b, ['species_id' => $common->id])->id]);
    app(AcceptTrade::class)->handle($good, $b);
    app(AcceptTrade::class)->handle($boring, $b);

    $this->actingAs($a)->get('/feed')
        ->assertInertia(fn (Assert $page) => $page->where(
            'entries',
            fn ($entries) => collect($entries)->where('kind', 'trade')->pluck('tradeId')->all() === [$good->id],
        ));
});
