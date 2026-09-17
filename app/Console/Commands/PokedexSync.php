<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PokemonType;
use App\Models\Species;
use App\Services\RarityTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PokedexSync extends Command
{
    protected $signature = 'pokedex:sync';

    protected $description = 'Importa as 151 espécies da primeira geração e baixa os sprites pro storage local';

    private const TOTAL_SPECIES = 151;

    private const API_BASE_URL = 'https://pokeapi.co/api/v2';

    public function handle(RarityTable $rarityTable): int
    {
        $this->output->progressStart(self::TOTAL_SPECIES);

        $missingFlavorText = [];

        for ($dexNumber = 1; $dexNumber <= self::TOTAL_SPECIES; $dexNumber++) {
            $pokemon = Http::get(self::API_BASE_URL."/pokemon/{$dexNumber}")->json();
            $speciesInfo = Http::get(self::API_BASE_URL."/pokemon-species/{$dexNumber}")->json();
            $chainId = (int) Str::afterLast(rtrim($speciesInfo['evolution_chain']['url'], '/'), '/');
            $chain = Http::get(self::API_BASE_URL."/evolution-chain/{$chainId}")->json();

            $flavorPt = $this->extractFlavorText($speciesInfo['flavor_text_entries']);

            if ($flavorPt === null) {
                $missingFlavorText[] = $dexNumber;
            }

            $attributes = [
                'name' => $pokemon['name'],
                'slug' => Str::slug($pokemon['name']),
                'type_1' => PokemonType::from($pokemon['types'][0]['type']['name']),
                'type_2' => isset($pokemon['types'][1])
                    ? PokemonType::from($pokemon['types'][1]['type']['name'])
                    : null,
                'base_stat_total' => collect($pokemon['stats'])->sum('base_stat'),
                'evolution_stage' => $this->resolveEvolutionStage($chain['chain'], $dexNumber) ?? 1,
                'base_height_m' => $pokemon['height'] / 10,
                'base_weight_kg' => $pokemon['weight'] / 10,
                'sprite_path' => $this->downloadSprite($pokemon['sprites']['front_default'], $dexNumber, 'sprite'),
                'sprite_shiny_path' => $this->downloadSprite($pokemon['sprites']['front_shiny'], $dexNumber, 'shiny'),
                'artwork_path' => $this->downloadSprite($pokemon['sprites']['other']['official-artwork']['front_default'], $dexNumber, 'artwork'),
                'flavor_pt' => $flavorPt,
            ];

            $attributes['rarity_tier'] = $rarityTable->classify(new Species(['id' => $dexNumber, ...$attributes]));

            Species::updateOrCreate(['id' => $dexNumber], $attributes);

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        if ($missingFlavorText !== []) {
            $this->warn(count($missingFlavorText).' espécie(s) sem descrição em pt-BR: '.implode(', ', $missingFlavorText));
        }

        $this->info('Pokédex sincronizada: '.self::TOTAL_SPECIES.' espécies.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function extractFlavorText(array $entries): ?string
    {
        foreach ($entries as $entry) {
            $lang = strtolower($entry['language']['name']);
            if (in_array($lang, ['pt-br', 'pt'], true)) {
                return trim(str_replace(["\n", "\f"], ' ', (string) $entry['flavor_text']));
            }
        }

        return null;
    }

    /**
     * Acha o estágio evolutivo (1, 2 ou 3) da espécie dentro da cadeia,
     * ignorando nós fora do range 1-151 (pré-evoluções adicionadas em
     * gerações futuras, como Pichu antes de Pikachu).
     *
     * @param  array<string, mixed>  $node
     */
    private function resolveEvolutionStage(array $node, int $targetDexNumber, int $stageSoFar = 0): ?int
    {
        $dexNumber = (int) Str::afterLast(rtrim($node['species']['url'], '/'), '/');
        $stage = $dexNumber <= self::TOTAL_SPECIES ? $stageSoFar + 1 : $stageSoFar;

        if ($dexNumber === $targetDexNumber) {
            return $stage;
        }

        foreach ($node['evolves_to'] as $child) {
            $found = $this->resolveEvolutionStage($child, $targetDexNumber, $stage);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function downloadSprite(string $url, int $dexNumber, string $kind): string
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'png';
        $path = "species/{$dexNumber}/{$kind}.{$extension}";

        Storage::disk('public')->put($path, Http::get($url)->body());

        return $path;
    }
}
