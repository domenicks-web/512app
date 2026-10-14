<?php

declare(strict_types=1);

use App\Enums\TradeItemSide;
use App\Enums\TradeStatus;
use App\Models\Friendship;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\TradeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Espécime numa abertura do próprio dono: a factory padrão criaria uma
 * abertura de outro jogador (e com ele mais uma espécie de avatar).
 *
 * @param  array<string, mixed>  $attributes
 */
function specimenOf(User $owner, array $attributes = []): Specimen
{
    return Specimen::factory()
        ->for($owner)
        ->for(Opening::factory()->for($owner))
        ->create($attributes);
}

it('exige login e perfil completo nas telas do grupo', function (string $url) {
    $this->get($url)->assertRedirect('/login');
    $this->actingAs(User::factory()->withoutProfile()->create())->get($url)->assertRedirect('/completar-perfil');
})->with(['/colecao', '/ranking', '/trocas', '/amigos', '/feed']);

it('lista todas as espécies na coleção com contagem de espécimes e shinies', function () {
    $user = User::factory()->create(['avatar_species_id' => null]);
    $other = User::factory()->create(['avatar_species_id' => null]);
    [$bulbasaur, $ivysaur] = Species::factory()->count(2)->create();
    specimenOf($user, ['species_id' => $bulbasaur->id]);
    specimenOf($user, ['species_id' => $bulbasaur->id]);
    specimenOf($user, ['species_id' => $bulbasaur->id, 'is_shiny' => true]);
    specimenOf($other, ['species_id' => $ivysaur->id]);

    $this->actingAs($user)->get('/colecao')
        ->assertInertia(fn (Assert $page) => $page
            ->component('collection/Show')
            ->has('species', 2)
            ->where('species.0.id', $bulbasaur->id)
            ->where('species.0.specimenCount', 3)
            ->where('species.0.shinyCount', 1)
            ->where('species.1.specimenCount', 0)
            ->where('ownedSpecies', 1)
            ->where('totalSpecimens', 3));
});

it('ordena o ranking por espécies e deixa quem não tem nada sem posição', function () {
    [$a, $b] = Species::factory()->count(2)->create();
    $first = User::factory()->create(['nickname' => 'Lin']);
    $second = User::factory()->create(['nickname' => 'Bia']);
    $empty = User::factory()->create(['nickname' => 'Zé']);
    User::factory()->withoutProfile()->create();
    specimenOf($first, ['species_id' => $a->id]);
    specimenOf($first, ['species_id' => $b->id]);
    specimenOf($second, ['species_id' => $a->id, 'is_shiny' => true]);

    $this->actingAs($empty)->get('/ranking')
        ->assertInertia(fn (Assert $page) => $page
            ->component('ranking/Show')
            ->where('viewerId', $empty->id)
            ->has('rows', 3)
            ->where('rows.0.nickname', 'Lin')
            ->where('rows.0.position', 1)
            ->where('rows.1.nickname', 'Bia')
            ->where('rows.1.shinyCount', 1)
            ->where('rows.1.position', 2)
            ->where('rows.2.nickname', 'Zé')
            ->where('rows.2.position', null));
});

it('separa trocas pendentes recebidas e enviadas, com os espécimes de cada lado', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create(['nickname' => 'Bia']);
    $pikachu = Species::factory()->create(['name' => 'Pikachu']);
    $incoming = Trade::factory()->create(['from_user_id' => $friend->id, 'to_user_id' => $user->id, 'message' => 'bora?']);
    TradeItem::factory()->for($incoming)->create([
        'specimen_id' => Specimen::factory()->for($friend)->create(['species_id' => $pikachu->id, 'is_shiny' => true, 'mint_number' => 7]),
        'side' => TradeItemSide::Offered,
    ]);
    TradeItem::factory()->for($incoming)->create([
        'specimen_id' => Specimen::factory()->for($user)->create(),
        'side' => TradeItemSide::Requested,
    ]);
    Trade::factory()->create(['from_user_id' => $user->id, 'to_user_id' => $friend->id]);
    Trade::factory()->create(['to_user_id' => $user->id, 'status' => TradeStatus::Accepted]);
    Trade::factory()->create();

    $this->actingAs($user)->get('/trocas')
        ->assertInertia(fn (Assert $page) => $page
            ->component('trades/Index')
            ->has('incoming', 1)
            ->where('incoming.0.otherTrainer.nickname', 'Bia')
            ->where('incoming.0.message', 'bora?')
            ->where('incoming.0.offered.0.speciesName', 'Pikachu')
            ->where('incoming.0.offered.0.isShiny', true)
            ->where('incoming.0.offered.0.mintNumber', 7)
            ->where('incoming.0.offered.0.spritePath', $pikachu->sprite_shiny_path)
            ->has('incoming.0.requested', 1)
            ->has('outgoing', 1)
            ->where('outgoing.0.otherTrainer.nickname', 'Bia'));
});

it('lista só os amigos, online primeiro, e separa os pedidos', function () {
    $user = User::factory()->create();
    $online = User::factory()->create(['nickname' => 'Zé']);
    $offline = User::factory()->create(['nickname' => 'Ana']);
    $stranger = User::factory()->create();
    $asking = User::factory()->create(['nickname' => 'Bia']);
    $asked = User::factory()->create(['nickname' => 'Caio']);
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $offline->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $online->id, 'addressee_id' => $user->id]);
    Friendship::factory()->create(['requester_id' => $asking->id, 'addressee_id' => $user->id]);
    Friendship::factory()->create(['requester_id' => $user->id, 'addressee_id' => $asked->id]);
    specimenOf($offline);
    DB::table('sessions')->insert([
        ['id' => Str::random(40), 'user_id' => $online->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ['id' => Str::random(40), 'user_id' => $stranger->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
    ]);

    $this->actingAs($user)->get('/amigos')
        ->assertInertia(fn (Assert $page) => $page
            ->component('friends/Index')
            ->has('friends', 2)
            ->where('friends.0.nickname', 'Zé')
            ->where('friends.0.isOnline', true)
            ->where('friends.1.nickname', 'Ana')
            ->where('friends.1.isOnline', false)
            ->where('friends.1.ownedSpecies', 1)
            ->has('incoming', 1)
            ->where('incoming.0.nickname', 'Bia')
            ->has('outgoing', 1)
            ->where('outgoing.0.nickname', 'Caio')
            ->where('presencePollSeconds', config('game.group.presence_poll_seconds')));
});

it('mostra "agora" em vez de "há 0 segundo" pra pedido recém-enviado', function () {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->create();
    Friendship::factory()->create(['requester_id' => $user->id]);

    $this->actingAs($user)->get('/amigos')
        ->assertInertia(fn (Assert $page) => $page->where('outgoing.0.sentAgo', 'agora'));
});

it('lista no feed só as aberturas boas, mais recentes primeiro, com quem abriu', function () {
    $this->travelTo(now()->startOfMinute());
    $opener = User::factory()->create(['nickname' => 'Ramon']);
    $rattata = Species::factory()->create(['name' => 'Rattata', 'sprite_shiny_path' => 'species/19/shiny.png']);
    $mewtwo = Species::factory()->legendary()->create(['name' => 'Mewtwo', 'sprite_path' => 'species/150/sprite.png']);
    specimenOf($opener, ['species_id' => $mewtwo->id, 'caught_at' => now()->subHour()]);
    specimenOf($opener, ['species_id' => $rattata->id, 'is_shiny' => true, 'mint_number' => 4, 'caught_at' => now()->subMinutes(12)]);
    specimenOf($opener, ['species_id' => $rattata->id, 'caught_at' => now()]);

    $this->actingAs(User::factory()->create())->get('/feed')
        ->assertInertia(fn (Assert $page) => $page
            ->component('feed/Show')
            ->has('entries', 2)
            ->where('entries.0.speciesName', 'Rattata')
            ->where('entries.0.isShiny', true)
            ->where('entries.0.spritePath', 'species/19/shiny.png')
            ->where('entries.0.trainerNickname', 'Ramon')
            ->where('entries.0.caughtAgo', 'há 12 minutos')
            ->where('entries.1.speciesName', 'Mewtwo')
            ->where('entries.1.rarityTier', 'lendario')
            ->where('entries.1.spritePath', 'species/150/sprite.png'));
});

it('limita o feed ao tamanho do config', function () {
    config(['game.group.feed_limit' => 2]);
    $species = Species::factory()->legendary()->create();
    Specimen::factory()->count(3)->create(['species_id' => $species->id]);

    $this->actingAs(User::factory()->create())->get('/feed')
        ->assertInertia(fn (Assert $page) => $page->has('entries', 2));
});
