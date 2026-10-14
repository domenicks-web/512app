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
})->with(['/colecao', '/ranking', '/trocas', '/amigos']);

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
