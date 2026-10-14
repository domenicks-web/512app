<?php

declare(strict_types=1);

use App\Enums\TradeStatus;
use App\Models\Friendship;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('mostra o treinador com o sprite do parceiro escolhido', function () {
    $species = Species::factory()->create(['sprite_path' => 'species/25/sprite.png']);
    $user = User::factory()->create(['nickname' => 'Lin', 'tag' => '6401', 'avatar_species_id' => $species->id]);

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('trainer.nickname', 'Lin')
            ->where('trainer.tag', '6401')
            ->where('trainer.avatarSpritePath', 'species/25/sprite.png'));
});

it('conta espécies distintas da coleção, não espécimes', function () {
    $user = User::factory()->create();
    [$pikachu, $rattata] = Species::factory()->count(2)->create();
    Specimen::factory()->count(3)->for($user)->create(['species_id' => $pikachu->id]);
    Specimen::factory()->for($user)->create(['species_id' => $rattata->id]);
    Specimen::factory()->create(['species_id' => $rattata->id]);

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('collection.ownedSpecies', 2)
            ->where('collection.totalSpecies', Species::count()));
});

it('mostra a posição no ranking de espécies e divide empate', function () {
    [$pikachu, $rattata] = Species::factory()->count(2)->create();
    $leader = User::factory()->create();
    $tied = User::factory()->create();
    $user = User::factory()->create();
    Specimen::factory()->for($leader)->create(['species_id' => $pikachu->id]);
    Specimen::factory()->for($leader)->create(['species_id' => $rattata->id]);
    Specimen::factory()->for($tied)->create(['species_id' => $pikachu->id]);
    Specimen::factory()->for($user)->create(['species_id' => $rattata->id]);

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('rankingPosition', 2));
});

it('não dá posição no ranking pra quem ainda não tem espécie', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('rankingPosition', null));
});

it('conta só as propostas de troca pendentes recebidas', function () {
    $user = User::factory()->create();
    Trade::factory()->count(2)->create(['to_user_id' => $user->id]);
    Trade::factory()->create(['to_user_id' => $user->id, 'status' => TradeStatus::Declined]);
    Trade::factory()->create(['from_user_id' => $user->id]);

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('pendingTrades', 2));
});

it('destaca no feed a abertura boa mais recente, com quem abriu', function () {
    $this->travelTo(now()->startOfMinute());
    $opener = User::factory()->create(['nickname' => 'Ramon']);
    $rattata = Species::factory()->create(['name' => 'Rattata']);
    $mewtwo = Species::factory()->legendary()->create(['name' => 'Mewtwo']);
    $openedByRamon = Opening::factory()->for($opener)->create();
    Specimen::factory()->create(['species_id' => $mewtwo->id, 'caught_at' => now()->subHour()]);
    Specimen::factory()->create([
        'opening_id' => $openedByRamon->id,
        'species_id' => $rattata->id,
        'is_shiny' => true,
        'mint_number' => 4,
        'caught_at' => now()->subMinutes(12),
    ]);
    Specimen::factory()->create(['species_id' => $rattata->id, 'caught_at' => now()]);

    $this->actingAs(User::factory()->create())->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('feedHighlight.isShiny', true)
            ->where('feedHighlight.trainerNickname', 'Ramon')
            ->where('feedHighlight.speciesName', 'Rattata')
            ->where('feedHighlight.mintNumber', 4)
            ->where('feedHighlight.caughtAgo', 'há 12 minutos'));
});

it('deixa o feed vazio quando ninguém abriu nada bom', function () {
    Specimen::factory()->create();

    $this->actingAs(User::factory()->create())->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('feedHighlight', null));
});

it('conta só amigos com sessão ativa na janela de online', function () {
    $user = User::factory()->create();
    $recent = User::factory()->create();
    $stale = User::factory()->create();
    $window = config('game.group.online_window_minutes');
    $session = fn (?int $userId, int $lastActivity) => DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $userId,
        'payload' => '',
        'last_activity' => $lastActivity,
    ]);
    $session($user->id, now()->getTimestamp());
    $session($recent->id, now()->subMinutes($window - 1)->getTimestamp());
    $session($recent->id, now()->getTimestamp());
    $session($stale->id, now()->subMinutes($window + 1)->getTimestamp());
    $session(null, now()->getTimestamp());
    $strangerOnline = User::factory()->create();
    $session($strangerOnline->id, now()->getTimestamp());
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $recent->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $stale->id, 'addressee_id' => $user->id]);

    $this->actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('friendsOnline', 1)
            ->where('presencePollSeconds', config('game.group.presence_poll_seconds')));
});

it('mostra quando sai o próximo pack ou deixa vazio se já está disponível', function () {
    $this->travelTo(now()->startOfSecond());
    $waiting = User::factory()->create(['next_pack_at' => now()->addHours(5)]);
    $ready = User::factory()->create(['next_pack_at' => now()->subMinute()]);

    $this->actingAs($waiting)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('nextPackAt', now()->addHours(5)->toIso8601String()));
    $this->actingAs($ready)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('nextPackAt', null));
});
