<?php

declare(strict_types=1);

use App\Enums\PokemonType;
use App\Enums\RarityTier;
use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $evolvesTo
 * @return array<string, mixed>
 */
function evolutionNode(int $dexId, array $evolvesTo): array
{
    return [
        'species' => [
            'name' => "species-{$dexId}",
            'url' => "https://pokeapi.co/api/v2/pokemon-species/{$dexId}/",
        ],
        'evolves_to' => $evolvesTo,
    ];
}

/**
 * @return array<string, mixed>
 */
function evolutionChainFixture(int $chainId): array
{
    return match ($chainId) {
        1 => ['chain' => evolutionNode(1, [evolutionNode(2, [evolutionNode(3, [])])])],
        // Pichu (172) é pré-evolução de Pikachu adicionada em gerações
        // futuras — fica fora do range 1-151 e não deve contar estágio.
        10 => ['chain' => evolutionNode(172, [evolutionNode(25, [evolutionNode(26, [])])])],
        150 => ['chain' => evolutionNode(150, [])],
        133 => ['chain' => evolutionNode(133, [])],
        default => ['chain' => evolutionNode($chainId - 1000, [])],
    };
}

function chainIdForDex(int $dexId): int
{
    return match (true) {
        in_array($dexId, [1, 2, 3], true) => 1,
        in_array($dexId, [25, 26], true) => 10,
        $dexId === 150 => 150,
        $dexId === 133 => 133,
        default => 1000 + $dexId,
    };
}

/**
 * @return array<string, mixed>
 */
function pokemonSpeciesFixture(int $dexId): array
{
    $entries = $dexId === 1
        ? [
            ['flavor_text' => 'Semente estranha nas costas desde o nascimento.', 'language' => ['name' => 'pt-BR']],
            ['flavor_text' => 'A strange seed was planted on its back at birth.', 'language' => ['name' => 'en']],
        ]
        : [['flavor_text' => 'Generic flavor text.', 'language' => ['name' => 'en']]];

    return [
        'flavor_text_entries' => $entries,
        'evolution_chain' => ['url' => 'https://pokeapi.co/api/v2/evolution-chain/'.chainIdForDex($dexId).'/'],
    ];
}

/**
 * @return array<string, mixed>
 */
function pokemonFixture(int $dexId): array
{
    $names = [1 => 'bulbasaur', 2 => 'ivysaur', 3 => 'venusaur', 25 => 'pikachu', 26 => 'raichu', 150 => 'mewtwo', 133 => 'eevee'];

    return [
        'name' => $names[$dexId] ?? "species-{$dexId}",
        'height' => 7,
        'weight' => 69,
        // Jigglypuff (39) simula um caso real: a PokeAPI devolve o tipo
        // ATUAL (fairy, introduzido na geração 6), mas o jogo é gen-1, então
        // o sync precisa recuperar o tipo original via `past_types`.
        'types' => $dexId === 39
            ? [['slot' => 1, 'type' => ['name' => 'fairy']]]
            : [['slot' => 1, 'type' => ['name' => 'grass']]],
        'past_types' => $dexId === 39
            ? [[
                'generation' => ['url' => 'https://pokeapi.co/api/v2/generation/6/'],
                'types' => [['slot' => 1, 'type' => ['name' => 'normal']]],
            ]]
            : [],
        'stats' => [
            ['base_stat' => 45, 'stat' => ['name' => 'hp']],
            ['base_stat' => 49, 'stat' => ['name' => 'attack']],
            ['base_stat' => 49, 'stat' => ['name' => 'defense']],
            ['base_stat' => 65, 'stat' => ['name' => 'special-attack']],
            ['base_stat' => 65, 'stat' => ['name' => 'special-defense']],
            ['base_stat' => 45, 'stat' => ['name' => 'speed']],
        ],
        'sprites' => [
            'front_default' => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/{$dexId}.png",
            'front_shiny' => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/shiny/{$dexId}.png",
            'other' => [
                'official-artwork' => [
                    'front_default' => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/{$dexId}.png",
                ],
            ],
        ],
        'species' => ['url' => "https://pokeapi.co/api/v2/pokemon-species/{$dexId}/"],
    ];
}

function fakePokeApi(): void
{
    Http::fake(function ($request) {
        $url = rtrim($request->url(), '/');

        if (str_contains($url, '/evolution-chain/')) {
            return Http::response(evolutionChainFixture((int) Str::afterLast($url, '/')));
        }

        if (str_contains($url, '/pokemon-species/')) {
            return Http::response(pokemonSpeciesFixture((int) Str::afterLast($url, '/')));
        }

        if (str_contains($url, '/pokemon/')) {
            return Http::response(pokemonFixture((int) Str::afterLast($url, '/')));
        }

        return Http::response('fake-png-bytes', 200, ['Content-Type' => 'image/png']);
    });
}

it('sincroniza as 151 espécies com raridade, estágio evolutivo e sprites', function () {
    fakePokeApi();
    Storage::fake('public');

    $this->artisan('pokedex:sync')->assertExitCode(0);

    expect(Species::count())->toBe(151);

    $bulbasaur = Species::find(1);
    expect($bulbasaur->name)->toBe('Bulbasaur')
        ->and($bulbasaur->slug)->toBe('bulbasaur')
        ->and($bulbasaur->evolution_stage)->toBe(1)
        ->and($bulbasaur->flavor_pt)->toBe('Semente estranha nas costas desde o nascimento.');

    expect(Species::find(2)->evolution_stage)->toBe(2);

    // Pichu (172) fica fora do range 1-151 e não deve contar como estágio.
    expect(Species::find(25)->evolution_stage)->toBe(1);
    expect(Species::find(26)->evolution_stage)->toBe(2);

    expect(Species::find(150)->rarity_tier)->toBe(RarityTier::Legendary);

    // Jigglypuff (39): a API devolve "fairy" (tipo atual, gen 6+), mas o
    // tipo original de gen 1 (via past_types) é "normal" — o enum do jogo
    // só tem os 15 tipos originais, então usar o tipo atual quebraria.
    expect(Species::find(39)->type_1)->toBe(PokemonType::Normal);

    Storage::disk('public')->assertExists($bulbasaur->sprite_path);
    Storage::disk('public')->assertExists($bulbasaur->sprite_shiny_path);
    Storage::disk('public')->assertExists($bulbasaur->artwork_path);
});

it('é idempotente ao rodar duas vezes seguidas', function () {
    fakePokeApi();
    Storage::fake('public');

    $this->artisan('pokedex:sync');
    $this->artisan('pokedex:sync');

    expect(Species::count())->toBe(151);
});
