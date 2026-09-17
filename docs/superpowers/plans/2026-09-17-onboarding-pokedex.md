# Onboarding pós-login, cadastro simplificado e pokedex:sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cadastro vira só convite + email + senha; um wizard pós-login (nickname → data de nascimento → avatar) completa o perfil; o avatar é um ícone de Pokémon escolhido entre 15 espécies reais, o que exige popular a Pokédex local pela primeira vez.

**Architecture:** Uma migration editada torna `nickname`/`tag`/`birthdate` nullable e troca `avatar_seed` por `avatar_species_id` (FK pra `species`). Um novo comando `pokedex:sync` popula as 151 espécies e baixa os sprites. O cadastro (`RegisterUser`) só cria a conta; uma nova Action `CompleteOnboarding` preenche o resto depois do primeiro login, atrás de uma página Inertia única com estado de passo local (sem requisição entre telas). Dois middlewares (`auth`, novo `onboarded`) guardam as rotas: deslogado cai no login, logado sem perfil cai no onboarding, o resto segue normal.

**Tech Stack:** Laravel 13 (PHP 8.4, tipagem estrita), Inertia v3 + Vue 3 (`<script setup>`), Tailwind v4, Pest 4, Pint.

**Spec:** `docs/superpowers/specs/2026-09-17-onboarding-pokedex-design.md`

## Global Constraints

- `declare(strict_types=1)` em todo arquivo PHP novo ou editado.
- Nenhum número mágico: a lista dos 15 avatares e qualquer taxa/constante do jogo vive em `config/game.php`, nunca hardcoded.
- Controller não tem lógica: valida via FormRequest, chama a Action, devolve Inertia/redirect. Se passar de 20 linhas por método, algo está no lugar errado.
- Vocabulário: `Species` é espécie, nunca "pokemon" solto no código.
- Nunca hotlinkar sprite: `pokedex:sync` baixa pro storage local; nada em runtime aponta pra URL externa.
- Nenhum sorteio ou decisão de jogo no cliente — o wizard só coleta escolha do jogador (nickname, data, avatar), não gera nada aleatório.
- Rodar `vendor/bin/pint --dirty` depois de qualquer mudança em arquivo PHP, antes de comitar.
- Migrations sempre com `down()` funcional.
- Commits em português, imperativo, sem prefixo de conventional commits — um commit por task, ao final dela.

---

### Task 1: Schema — perfil opcional até o onboarding + `avatar_species_id`

**Files:**
- Modify: `database/migrations/2026_09_15_145533_add_cadastro_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/Models/UserTest.php`

**Interfaces:**
- Consumes: nada (task de fundação).
- Produces: `User::hasCompletedProfile(): bool`, `User::avatarSpecies(): BelongsTo<Species, User>`, `UserFactory::withoutProfile(): static` — usados pelas Tasks 3, 4, 5, 8.

- [ ] **Step 1: Escrever o teste (falhando)**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('considera o perfil completo quando o usuário tem nickname', function () {
    $user = User::factory()->create();

    expect($user->hasCompletedProfile())->toBeTrue();
});

it('considera o perfil incompleto quando falta o nickname', function () {
    $user = User::factory()->withoutProfile()->create();

    expect($user->hasCompletedProfile())->toBeFalse();
});
```

Salvar em `tests/Feature/Models/UserTest.php`.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Feature/Models/UserTest.php`
Expected: FAIL — `hasCompletedProfile` não existe, `withoutProfile` não existe.

- [ ] **Step 3: Editar a migration**

Substituir o conteúdo de `database/migrations/2026_09_15_145533_add_cadastro_fields_to_users_table.php` por:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('id');
            $table->string('tag', 4)->nullable()->after('nickname');
            $table->date('birthdate')->nullable()->after('email_verified_at');
            $table->unsignedSmallInteger('avatar_species_id')->nullable()->after('birthdate');
            $table->boolean('is_admin')->default(false)->after('avatar_species_id');

            $table->unique(['nickname', 'tag']);
            $table->foreign('avatar_species_id')->references('id')->on('species');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['avatar_species_id']);
            $table->dropUnique(['nickname', 'tag']);
            $table->dropColumn(['nickname', 'tag', 'birthdate', 'avatar_species_id', 'is_admin']);
        });
    }
};
```

`species` já existe nesse ponto da timeline de migrations (`2026_09_13_071635_create_species_table.php` é anterior), então a FK pode ser criada direto aqui.

- [ ] **Step 4: Atualizar `App\Models\User`**

Trocar o import e o array `#[Fillable]` no topo, e adicionar os dois métodos novos:

```php
<?php

namespace App\Models;

use App\Enums\PokemonType;
use App\Enums\RarityTier;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $nickname
 * @property string|null $tag
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $birthdate
 * @property int|null $avatar_species_id
 * @property bool $is_admin
 * @property int|null $invited_by
 * @property string $password
 * @property string|null $remember_token
 * @property string $client_seed
 * @property int $nonce
 * @property Carbon|null $next_pack_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nickname', 'tag', 'email', 'password', 'birthdate', 'avatar_species_id', 'invited_by', 'client_seed'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsTo<Invite, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Invite::class, 'invited_by');
    }

    /**
     * @return HasMany<Invite, $this>
     */
    public function createdInvites(): HasMany
    {
        return $this->hasMany(Invite::class, 'created_by');
    }

    /**
     * @return HasMany<Opening, $this>
     */
    public function openings(): HasMany
    {
        return $this->hasMany(Opening::class);
    }

    /**
     * @return HasMany<Specimen, $this>
     */
    public function specimens(): HasMany
    {
        return $this->hasMany(Specimen::class);
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function avatarSpecies(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'avatar_species_id');
    }

    public function hasCompletedProfile(): bool
    {
        return $this->nickname !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'nonce' => 'integer',
            'next_pack_at' => 'datetime',
        ];
    }
}
```

(Os imports `PokemonType`/`RarityTier` do topo original não eram usados — removidos junto; se o linter reclamar de import não usado em algum outro trecho, ignore, não existiam no arquivo original.)

- [ ] **Step 5: Atualizar `UserFactory`**

Em `database/factories/UserFactory.php`, trocar a linha `'avatar_seed' => Str::random(16),` por `'avatar_species_id' => Species::factory(),` (adicionar `use App\Models\Species;` no topo), e adicionar o state novo:

```php
<?php

namespace Database\Factories;

use App\Models\Species;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nickname' => fake()->unique()->firstName(),
            'tag' => str_pad((string) fake()->unique()->numberBetween(0, 9999), 4, '0', STR_PAD_LEFT),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'birthdate' => fake()->date(),
            'avatar_species_id' => Species::factory(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'client_seed' => Str::random(32),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user ainda não passou pelo onboarding pós-login.
     */
    public function withoutProfile(): static
    {
        return $this->state(fn (): array => [
            'nickname' => null,
            'tag' => null,
            'birthdate' => null,
            'avatar_species_id' => null,
        ]);
    }
}
```

- [ ] **Step 6: Rodar a migration do zero e o teste**

Run: `php artisan migrate:fresh --no-interaction && php artisan test tests/Feature/Models/UserTest.php`
Expected: PASS nos dois testes.

- [ ] **Step 7: Rodar Pint e commitar**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_09_15_145533_add_cadastro_fields_to_users_table.php app/Models/User.php database/factories/UserFactory.php tests/Feature/Models/UserTest.php
git commit -m "Torna nickname/tag/data de nascimento opcionais e troca avatar_seed por avatar_species_id"
```

---

### Task 2: Comando `pokedex:sync`

**Files:**
- Create: `app/Console/Commands/PokedexSync.php`
- Test: `tests/Feature/Console/PokedexSyncTest.php`

**Interfaces:**
- Consumes: `App\Services\RarityTable::classify(Species $species): RarityTier` (já existe), `App\Models\Species` (já existe).
- Produces: comando `pokedex:sync` — popula 151 linhas em `species` e os arquivos em `storage/app/public/species/{id}/{sprite,shiny,artwork}.png`. Nenhuma outra task depende do código deste comando (as Tasks 4-6 usam `Species::factory()` nos testes), mas ele é pré-requisito pra ter dado de verdade em ambiente local.

- [ ] **Step 1: Escrever o teste (falhando)**

Criar `tests/Feature/Console/PokedexSyncTest.php`:

```php
<?php

declare(strict_types=1);

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
        'types' => [['slot' => 1, 'type' => ['name' => 'grass']]],
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
    expect($bulbasaur->name)->toBe('bulbasaur')
        ->and($bulbasaur->evolution_stage)->toBe(1)
        ->and($bulbasaur->flavor_pt)->toBe('Semente estranha nas costas desde o nascimento.');

    expect(Species::find(2)->evolution_stage)->toBe(2);

    // Pichu (172) fica fora do range 1-151 e não deve contar como estágio.
    expect(Species::find(25)->evolution_stage)->toBe(1);
    expect(Species::find(26)->evolution_stage)->toBe(2);

    expect(Species::find(150)->rarity_tier)->toBe(RarityTier::Legendary);

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
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Feature/Console/PokedexSyncTest.php`
Expected: FAIL — comando `pokedex:sync` não existe.

- [ ] **Step 3: Criar o comando**

Criar `app/Console/Commands/PokedexSync.php`:

```php
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
            $chainId = (int) Str::of($speciesInfo['evolution_chain']['url'])->rtrim('/')->afterLast('/');
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
            if (in_array($entry['language']['name'], ['pt-br', 'pt'], true)) {
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
        $dexNumber = (int) Str::of($node['species']['url'])->rtrim('/')->afterLast('/');
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
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test tests/Feature/Console/PokedexSyncTest.php`
Expected: PASS nos dois testes.

- [ ] **Step 5: Pint e commit**

```bash
vendor/bin/pint --dirty
git add app/Console/Commands/PokedexSync.php tests/Feature/Console/PokedexSyncTest.php
git commit -m "Adiciona o comando pokedex:sync que importa as 151 espécies e baixa os sprites"
```

- [ ] **Step 6 (manual, fora do TDD): rodar contra a API de verdade em ambiente local**

Run: `php artisan pokedex:sync`
Expected: 151 espécies em `species`, arquivos reais em `storage/app/public/species/`. Precisa de `php artisan storage:link` já feito e conexão de internet. Se der timeout de rede, não é erro do código — rodar de novo (idempotente).

---

### Task 3: Cadastro simplificado (só convite + email + senha)

**Files:**
- Modify: `app/Http/Requests/RegisterUserRequest.php`
- Modify: `app/Actions/Data/RegisterUserData.php`
- Modify: `app/Actions/RegisterUser.php`
- Modify: `resources/js/pages/auth/Register.vue`
- Modify: `tests/Feature/Actions/RegisterUserTest.php`
- Modify: `tests/Feature/Http/RegisterUserControllerTest.php`

**Interfaces:**
- Consumes: colunas nullable de `users` (Task 1).
- Produces: `RegisterUserData(string $email, string $password, string $inviteCode)` — o formato que a Task 5 (onboarding) não usa diretamente, mas que precisa estar estável pro resto do fluxo de cadastro.

- [ ] **Step 1: Atualizar os testes existentes primeiro (vão falhar)**

Em `tests/Feature/Actions/RegisterUserTest.php`, trocar a função `registerUserData()` e a primeira asserção:

```php
<?php

declare(strict_types=1);

use App\Actions\Data\RegisterUserData;
use App\Actions\RegisterUser;
use App\Exceptions\InviteNotRedeemableException;
use App\Models\Invite;
use App\Models\InviteRedemption;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function registerUserData(array $overrides = []): RegisterUserData
{
    $defaults = [
        'email' => 'lin@example.com',
        'password' => 'segredo123',
        'inviteCode' => 'RAMON-7X2K',
    ];

    return new RegisterUserData(...array_merge($defaults, $overrides));
}

it('cria o usuário com perfil pendente, resgata o convite e envia a verificação de email', function () {
    Notification::fake();

    $invite = Invite::factory()->create(['code' => 'RAMON-7X2K', 'max_uses' => 1]);

    $user = (new RegisterUser)->handle(registerUserData());

    expect($user->nickname)->toBeNull()
        ->and($user->tag)->toBeNull()
        ->and($user->hasCompletedProfile())->toBeFalse()
        ->and($user->email)->toBe('lin@example.com')
        ->and(Hash::check('segredo123', $user->password))->toBeTrue()
        ->and($user->invited_by)->toBe($invite->id);

    $invite->refresh();
    expect($invite->uses_count)->toBe(1);

    expect(InviteRedemption::where('invite_id', $invite->id)->where('user_id', $user->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejeita convite inexistente', function () {
    (new RegisterUser)->handle(registerUserData(['inviteCode' => 'NAO-EXISTE']));
})->throws(InviteNotRedeemableException::class);

it('rejeita convite esgotado', function () {
    Invite::factory()->exhausted()->create(['code' => 'RAMON-7X2K']);

    (new RegisterUser)->handle(registerUserData());
})->throws(InviteNotRedeemableException::class);

it('rejeita convite expirado', function () {
    Invite::factory()->expired()->create(['code' => 'RAMON-7X2K', 'max_uses' => 1]);

    (new RegisterUser)->handle(registerUserData());
})->throws(InviteNotRedeemableException::class);

it('aceita convite sem limite de usos mesmo depois de vários resgates', function () {
    Notification::fake();

    $invite = Invite::factory()->unlimited()->create(['code' => 'RAMON-7X2K', 'uses_count' => 50]);

    $user = (new RegisterUser)->handle(registerUserData());

    expect($user->invited_by)->toBe($invite->id);
});
```

Em `tests/Feature/Http/RegisterUserControllerTest.php`, trocar `validRegistrationPayload()`:

```php
function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'lin@example.com',
        'password' => 'segredo123',
        'password_confirmation' => 'segredo123',
        'invite_code' => 'RAMON-7X2K',
    ], $overrides);
}
```

O resto do arquivo (`it('cadastra e autentica...')`, `it('rejeita cadastro sem código...')`, `it('devolve erro...')`) fica igual, só sem `nickname`/`birthdate` no payload.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Feature/Actions/RegisterUserTest.php tests/Feature/Http/RegisterUserControllerTest.php`
Expected: FAIL — `RegisterUserData` ainda exige `nickname`/`birthdate`.

- [ ] **Step 3: Atualizar `RegisterUserData`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\Data;

final readonly class RegisterUserData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $inviteCode,
    ) {}
}
```

- [ ] **Step 4: Atualizar `RegisterUserRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Data\RegisterUserData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
            'invite_code' => ['required', 'string', 'exists:invites,code'],
        ];
    }

    public function toRegisterUserData(): RegisterUserData
    {
        return new RegisterUserData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            inviteCode: $this->string('invite_code')->toString(),
        );
    }
}
```

- [ ] **Step 5: Atualizar `RegisterUser`**

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Data\RegisterUserData;
use App\Exceptions\InviteNotRedeemableException;
use App\Models\Invite;
use App\Models\InviteRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUser
{
    public function handle(RegisterUserData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $invite = Invite::where('code', $data->inviteCode)->lockForUpdate()->first();

            if ($invite === null || $invite->isExpired() || ! $invite->hasUsesLeft()) {
                throw InviteNotRedeemableException::code($data->inviteCode);
            }

            $user = User::create([
                'email' => $data->email,
                'password' => $data->password,
                'invited_by' => $invite->id,
                'client_seed' => Str::random(32),
            ]);

            InviteRedemption::create([
                'invite_id' => $invite->id,
                'user_id' => $user->id,
            ]);

            $invite->increment('uses_count');

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return $user;
    }
}
```

(`GenerateUserTag` sai daqui — não é mais chamado no cadastro, só no onboarding, Task 4.)

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `php artisan test tests/Feature/Actions/RegisterUserTest.php tests/Feature/Http/RegisterUserControllerTest.php`
Expected: PASS.

- [ ] **Step 7: Atualizar `Register.vue`**

Substituir `resources/js/pages/auth/Register.vue` por:

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/Auth/RegisterUserController';
import { login } from '@/routes';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/AppInput.vue';
import BrandMark from '@/components/BrandMark.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
</script>

<template>
    <Head title="Cadastro" />

    <div
        class="bg-base relative flex min-h-screen items-center justify-center overflow-hidden p-6"
    >
        <div
            class="bg-brand pointer-events-none absolute top-0 left-1/2 hidden h-[28rem] w-[28rem] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-20 blur-[120px] dark:block"
            aria-hidden="true"
        />

        <div class="absolute top-6 right-6">
            <ThemeToggle />
        </div>

        <div
            class="bg-surface ring-ink/10 relative w-full max-w-sm rounded-3xl p-8 shadow-xl ring-1 backdrop-blur-xl ring-inset"
        >
            <div class="flex flex-col items-center text-center">
                <BrandMark />
                <h1 class="font-display text-ink mt-6 text-2xl font-extrabold">
                    Cria sua conta
                </h1>
                <p class="text-ink/60 mt-2 text-sm">
                    Precisa de um código de convite de quem já joga
                </p>
            </div>

            <Form
                v-bind="store()"
                reset-on-error
                class="mt-8 flex flex-col gap-4"
                #default="{ errors, processing }"
            >
                <AppInput
                    label="Email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    :error="errors.email"
                />

                <AppInput
                    label="Senha"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                    :error="errors.password"
                />

                <AppInput
                    label="Confirme a senha"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />

                <AppInput
                    label="Código de convite"
                    name="invite_code"
                    autocomplete="off"
                    required
                    :error="errors.invite_code"
                />

                <AppButton
                    type="submit"
                    :disabled="processing"
                    class="mt-2 w-full"
                >
                    {{ processing ? 'Criando conta...' : 'Criar conta' }}
                </AppButton>
            </Form>

            <p class="text-ink/40 mt-6 text-center text-xs">
                Já tem conta?
                <Link
                    :href="login()"
                    class="text-brand cursor-pointer font-bold hover:underline"
                    >Entrar</Link
                >
            </p>
        </div>
    </div>
</template>
```

- [ ] **Step 8: Pint, type-check e commit**

```bash
vendor/bin/pint --dirty
npm run types:check
git add app/Http/Requests/RegisterUserRequest.php app/Actions/Data/RegisterUserData.php app/Actions/RegisterUser.php resources/js/pages/auth/Register.vue tests/Feature/Actions/RegisterUserTest.php tests/Feature/Http/RegisterUserControllerTest.php
git commit -m "Simplifica o cadastro pra só convite, email e senha"
```

---

### Task 4: Lista de avatares + Action `CompleteOnboarding`

**Files:**
- Modify: `config/game.php`
- Create: `app/Actions/Data/CompleteOnboardingData.php`
- Create: `app/Actions/CompleteOnboarding.php`
- Test: `tests/Feature/Actions/CompleteOnboardingTest.php`

**Interfaces:**
- Consumes: `App\Actions\GenerateUserTag::handle(string $nickname): string` (já existe), `User::update()`.
- Produces: `CompleteOnboardingData(string $nickname, CarbonInterface $birthdate, int $avatarSpeciesId)`, `CompleteOnboarding::handle(User $user, CompleteOnboardingData $data): User` — usado pela Task 5.

- [ ] **Step 1: Escrever o teste (falhando)**

Criar `tests/Feature/Actions/CompleteOnboardingTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\CompleteOnboarding;
use App\Actions\Data\CompleteOnboardingData;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preenche nickname, tag, data de nascimento e avatar', function () {
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();

    $result = (new CompleteOnboarding)->handle($user, new CompleteOnboardingData(
        nickname: 'Ramon',
        birthdate: now()->subYears(25),
        avatarSpeciesId: $species->id,
    ));

    expect($result->nickname)->toBe('Ramon')
        ->and($result->tag)->toMatch('/^\d{4}$/')
        ->and($result->avatar_species_id)->toBe($species->id)
        ->and($result->hasCompletedProfile())->toBeTrue();
});

it('gera tags diferentes pra nicknames repetidos', function () {
    User::factory()->create(['nickname' => 'Ramon', 'tag' => '0001']);
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();

    $result = (new CompleteOnboarding)->handle($user, new CompleteOnboardingData(
        nickname: 'Ramon',
        birthdate: now()->subYears(25),
        avatarSpeciesId: $species->id,
    ));

    expect($result->tag)->not->toBe('0001');
});
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Feature/Actions/CompleteOnboardingTest.php`
Expected: FAIL — classes não existem.

- [ ] **Step 3: Adicionar a lista de avatares em `config/game.php`**

Adicionar este bloco depois da seção `'size'` e antes de `'specimen_secret'`:

```php
    /*
    |--------------------------------------------------------------------------
    | Onboarding
    |--------------------------------------------------------------------------
    |
    | Espécies disponíveis como avatar no wizard pós-cadastro. Ids batem com
    | `species.id` (dex number, 1 a 151).
    |
    */

    'onboarding' => [
        'avatar_species_ids' => [
            25,  // Pikachu
            1,   // Bulbasaur
            4,   // Charmander
            7,   // Squirtle
            6,   // Charizard
            133, // Eevee
            143, // Snorlax
            94,  // Gengar
            150, // Mewtwo
            151, // Mew
            39,  // Jigglypuff
            54,  // Psyduck
            130, // Gyarados
            149, // Dragonite
            52,  // Meowth
        ],
    ],

```

- [ ] **Step 4: Criar `CompleteOnboardingData`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\Data;

use Carbon\CarbonInterface;

final readonly class CompleteOnboardingData
{
    public function __construct(
        public string $nickname,
        public CarbonInterface $birthdate,
        public int $avatarSpeciesId,
    ) {}
}
```

- [ ] **Step 5: Criar `CompleteOnboarding`**

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Data\CompleteOnboardingData;
use App\Models\User;

class CompleteOnboarding
{
    public function __construct(private readonly GenerateUserTag $generateUserTag = new GenerateUserTag) {}

    public function handle(User $user, CompleteOnboardingData $data): User
    {
        $user->update([
            'nickname' => $data->nickname,
            'tag' => $this->generateUserTag->handle($data->nickname),
            'birthdate' => $data->birthdate,
            'avatar_species_id' => $data->avatarSpeciesId,
        ]);

        return $user->refresh();
    }
}
```

A validade do `avatar_species_id` (pertencer à lista de 15) é responsabilidade do `CompleteOnboardingRequest` na Task 5 — a Action confia no dado já validado na borda, igual o resto do projeto faz.

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `php artisan test tests/Feature/Actions/CompleteOnboardingTest.php`
Expected: PASS.

- [ ] **Step 7: Pint e commit**

```bash
vendor/bin/pint --dirty
git add config/game.php app/Actions/Data/CompleteOnboardingData.php app/Actions/CompleteOnboarding.php tests/Feature/Actions/CompleteOnboardingTest.php
git commit -m "Adiciona a Action CompleteOnboarding e a lista de avatares disponíveis"
```

---

### Task 5: Rotas e controllers do onboarding

**Files:**
- Create: `app/Http/Requests/CompleteOnboardingRequest.php`
- Create: `app/Http/Controllers/Onboarding/ShowOnboardingController.php`
- Create: `app/Http/Controllers/Onboarding/CompleteOnboardingController.php`
- Modify: `routes/auth.php`
- Test: `tests/Feature/Http/CompleteOnboardingControllerTest.php`

**Interfaces:**
- Consumes: `CompleteOnboarding::handle()` e `CompleteOnboardingData` (Task 4), `User::hasCompletedProfile()` (Task 1).
- Produces: rotas nomeadas `onboarding.show` (`GET completar-perfil`) e `onboarding.store` (`POST completar-perfil`) — usadas pela Task 6 (Vue) e pela Task 8 (guard redireciona pra `onboarding.show`).

- [ ] **Step 1: Escrever o teste (falhando)**

Criar `tests/Feature/Http/CompleteOnboardingControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mostra o onboarding pra quem ainda não tem perfil', function () {
    $user = User::factory()->withoutProfile()->create();

    $response = $this->actingAs($user)->get('/completar-perfil');

    $response->assertOk();
});

it('manda pra home quem já completou o perfil e tenta abrir o onboarding', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/completar-perfil');

    $response->assertRedirect(route('home'));
});

it('completa o perfil e redireciona pra home', function () {
    $user = User::factory()->withoutProfile()->create();
    $species = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$species->id]]);

    $response = $this->actingAs($user)->post('/completar-perfil', [
        'nickname' => 'Ramon',
        'birthdate' => '2000-01-01',
        'avatar_species_id' => $species->id,
    ]);

    $response->assertRedirect(route('home'));
    expect($user->fresh()->nickname)->toBe('Ramon');
});

it('rejeita avatar fora da lista permitida', function () {
    $user = User::factory()->withoutProfile()->create();
    $allowed = Species::factory()->create();
    $disallowed = Species::factory()->create();
    config(['game.onboarding.avatar_species_ids' => [$allowed->id]]);

    $response = $this->actingAs($user)->post('/completar-perfil', [
        'nickname' => 'Ramon',
        'birthdate' => '2000-01-01',
        'avatar_species_id' => $disallowed->id,
    ]);

    $response->assertSessionHasErrors('avatar_species_id');
});
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Feature/Http/CompleteOnboardingControllerTest.php`
Expected: FAIL — rota `completar-perfil` não existe (404).

- [ ] **Step 3: Criar `CompleteOnboardingRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Data\CompleteOnboardingData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'min:2', 'max:20'],
            'birthdate' => ['required', 'date', 'before:today'],
            'avatar_species_id' => ['required', 'integer', Rule::in(config('game.onboarding.avatar_species_ids'))],
        ];
    }

    public function toCompleteOnboardingData(): CompleteOnboardingData
    {
        return new CompleteOnboardingData(
            nickname: $this->string('nickname')->toString(),
            birthdate: Carbon::parse($this->string('birthdate')->toString()),
            avatarSpeciesId: $this->integer('avatar_species_id'),
        );
    }
}
```

- [ ] **Step 4: Criar os controllers**

`app/Http/Controllers/Onboarding/ShowOnboardingController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Species;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowOnboardingController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasCompletedProfile()) {
            return redirect()->route('home');
        }

        $avatarOptions = Species::whereIn('id', config('game.onboarding.avatar_species_ids'))
            ->get(['id', 'name', 'artwork_path']);

        return Inertia::render('onboarding/Show', [
            'avatarOptions' => $avatarOptions,
        ]);
    }
}
```

`app/Http/Controllers/Onboarding/CompleteOnboardingController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\CompleteOnboarding;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteOnboardingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CompleteOnboardingController extends Controller
{
    public function store(CompleteOnboardingRequest $request, CompleteOnboarding $action): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $action->handle($user, $request->toCompleteOnboardingData());

        return redirect()->route('home');
    }
}
```

- [ ] **Step 5: Registrar as rotas**

Em `routes/auth.php`, adicionar os imports e as duas rotas dentro do grupo `auth` já existente:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginUserController;
use App\Http\Controllers\Auth\RegisterUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Onboarding\CompleteOnboardingController;
use App\Http\Controllers\Onboarding\ShowOnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::inertia('login', 'auth/Login')->name('login');
    Route::post('login', [LoginUserController::class, 'store'])->name('login.store');
    Route::inertia('cadastro', 'auth/Register')->name('register');
    Route::post('cadastro', [RegisterUserController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verificar-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('completar-perfil', ShowOnboardingController::class)->name('onboarding.show');
    Route::post('completar-perfil', [CompleteOnboardingController::class, 'store'])->name('onboarding.store');
});
```

(A rota de `logout` entra na Task 7, nesse mesmo grupo `auth`.)

- [ ] **Step 6: Gerar os tipos do Wayfinder**

Run: `php artisan wayfinder:generate --no-interaction`
Expected: cria `resources/js/actions/App/Http/Controllers/Onboarding/*.ts` e as entradas em `resources/js/routes`.

- [ ] **Step 7: Rodar e confirmar que passa**

Run: `php artisan test tests/Feature/Http/CompleteOnboardingControllerTest.php`
Expected: PASS.

- [ ] **Step 8: Pint e commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/CompleteOnboardingRequest.php app/Http/Controllers/Onboarding routes/auth.php resources/js/actions/App/Http/Controllers/Onboarding resources/js/routes tests/Feature/Http/CompleteOnboardingControllerTest.php
git commit -m "Adiciona as rotas e controllers do onboarding pós-login"
```

---

### Task 6: Página do wizard (Inertia + Vue)

**Files:**
- Modify: `resources/js/components/AppInput.vue`
- Create: `resources/js/pages/onboarding/Show.vue`

**Interfaces:**
- Consumes: rota `onboarding.store` (Task 5, via `store()` gerado pelo Wayfinder em `@/actions/App/Http/Controllers/Onboarding/CompleteOnboardingController`), prop `avatarOptions: {id: number, name: string, artwork_path: string}[]`.
- Produces: `AppInput` passa a aceitar `v-model` opcional (compatível com o uso atual por `name`, sem quebrar `Login.vue`/`Register.vue`).

- [ ] **Step 1: Adicionar suporte a `v-model` no `AppInput`**

O `AppInput` atual só funciona com o componente `<Form>` do Inertia (que lê o DOM pelo atributo `name`). O wizard precisa de estado controlado (nickname/data precisam ficar acessíveis entre os 3 passos pra montar um único POST no final), então o input precisa de `v-model` também — sem quebrar o uso existente.

Substituir `resources/js/components/AppInput.vue` por:

```vue
<script setup lang="ts">
const model = defineModel<string>({ default: '' });

withDefaults(
    defineProps<{
        label: string;
        name: string;
        type?: string;
        autocomplete?: string;
        required?: boolean;
        error?: string;
    }>(),
    {
        type: 'text',
        autocomplete: 'off',
        required: false,
        error: undefined,
    },
);
</script>

<template>
    <label class="flex flex-col gap-1.5 text-left">
        <span class="text-ink/70 text-sm font-bold">{{ label }}</span>
        <input
            v-model="model"
            :type="type"
            :name="name"
            :autocomplete="autocomplete"
            :required="required"
            class="border-ink/15 bg-ink/5 text-ink placeholder:text-ink/30 focus:border-brand focus:ring-brand/30 rounded-full border px-5 py-2.5 text-sm outline-none focus:ring-2"
            :class="error ? 'border-red-400' : ''"
        />
        <span v-if="error" class="text-xs font-semibold text-red-500">{{
            error
        }}</span>
    </label>
</template>
```

Quando o pai não passa `v-model`, o `defineModel` cria um estado interno próprio (default `''`) — `Login.vue` e `Register.vue` continuam funcionando exatamente igual, porque o `<Form>` do Inertia lê o valor do DOM no submit, não o estado interno do Vue.

- [ ] **Step 2: Criar a página do wizard**

Criar `resources/js/pages/onboarding/Show.vue`:

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Onboarding/CompleteOnboardingController';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/AppInput.vue';
import BrandMark from '@/components/BrandMark.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';

type AvatarOption = {
    id: number;
    name: string;
    artwork_path: string;
};

const props = defineProps<{
    avatarOptions: AvatarOption[];
}>();

const step = ref<1 | 2 | 3>(1);

const form = useForm({
    nickname: '',
    birthdate: '',
    avatar_species_id: null as number | null,
});

const canAdvanceFromStep1 = computed(() => form.nickname.trim().length >= 2);
const canAdvanceFromStep2 = computed(() => form.birthdate.length > 0);

function goToStep(target: 1 | 2 | 3) {
    step.value = target;
}

function submit() {
    form.post(store().url);
}
</script>

<template>
    <Head title="Complete seu perfil" />

    <div
        class="bg-base relative flex min-h-screen items-center justify-center overflow-hidden p-6"
    >
        <div
            class="bg-brand pointer-events-none absolute top-0 left-1/2 hidden h-[28rem] w-[28rem] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-20 blur-[120px] dark:block"
            aria-hidden="true"
        />

        <div class="absolute top-6 right-6">
            <ThemeToggle />
        </div>

        <div
            class="bg-surface ring-ink/10 relative w-full max-w-sm rounded-3xl p-8 shadow-xl ring-1 backdrop-blur-xl ring-inset"
        >
            <div class="flex flex-col items-center text-center">
                <BrandMark />
                <h1 class="font-display text-ink mt-6 text-2xl font-extrabold">
                    Quase lá
                </h1>
                <p class="text-ink/60 mt-2 text-sm">Passo {{ step }} de 3</p>
            </div>

            <form
                v-if="step === 1"
                class="mt-8 flex flex-col gap-4"
                @submit.prevent="goToStep(2)"
            >
                <AppInput
                    v-model="form.nickname"
                    label="Como quer ser chamado?"
                    name="nickname"
                    autocomplete="nickname"
                    required
                    :error="form.errors.nickname"
                />

                <AppButton
                    type="submit"
                    :disabled="!canAdvanceFromStep1"
                    class="mt-2 w-full"
                >
                    Continuar
                </AppButton>
            </form>

            <form
                v-else-if="step === 2"
                class="mt-8 flex flex-col gap-4"
                @submit.prevent="goToStep(3)"
            >
                <AppInput
                    v-model="form.birthdate"
                    label="Sua data de nascimento"
                    name="birthdate"
                    type="date"
                    autocomplete="bday"
                    required
                    :error="form.errors.birthdate"
                />

                <div class="mt-2 flex gap-3">
                    <AppButton
                        type="button"
                        variant="secondary"
                        class="w-full"
                        @click="goToStep(1)"
                    >
                        Voltar
                    </AppButton>
                    <AppButton
                        type="submit"
                        :disabled="!canAdvanceFromStep2"
                        class="w-full"
                    >
                        Continuar
                    </AppButton>
                </div>
            </form>

            <form
                v-else
                class="mt-8 flex flex-col gap-4"
                @submit.prevent="submit"
            >
                <p class="text-ink/70 text-sm font-bold">
                    Escolha seu avatar
                </p>

                <div class="grid grid-cols-5 gap-3">
                    <button
                        v-for="option in props.avatarOptions"
                        :key="option.id"
                        type="button"
                        class="ring-ink/10 flex aspect-square cursor-pointer items-center justify-center rounded-full bg-white p-2 ring-1 transition"
                        :class="
                            form.avatar_species_id === option.id
                                ? 'ring-brand ring-2'
                                : ''
                        "
                        @click="form.avatar_species_id = option.id"
                    >
                        <img
                            :src="`/storage/${option.artwork_path}`"
                            :alt="option.name"
                            class="h-full w-full object-contain"
                        />
                    </button>
                </div>

                <span
                    v-if="form.errors.avatar_species_id"
                    class="text-xs font-semibold text-red-500"
                    >{{ form.errors.avatar_species_id }}</span
                >

                <div class="mt-2 flex gap-3">
                    <AppButton
                        type="button"
                        variant="secondary"
                        class="w-full"
                        @click="goToStep(2)"
                    >
                        Voltar
                    </AppButton>
                    <AppButton
                        type="submit"
                        :disabled="
                            form.avatar_species_id === null || form.processing
                        "
                        class="w-full"
                    >
                        {{ form.processing ? 'Salvando...' : 'Entrar no jogo' }}
                    </AppButton>
                </div>
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Type-check**

Run: `npm run types:check`
Expected: sem erros. Se `store()` não for encontrado, rodar de novo `php artisan wayfinder:generate --no-interaction` (Task 5, Step 6).

- [ ] **Step 4: Verificação manual no navegador**

Suba o ambiente (`composer run dev` ou `npm run dev` + `php artisan serve`, o que já estiver de convenção no projeto), crie um usuário sem perfil (via tinker: `User::factory()->withoutProfile()->create(['email' => 'teste@teste.com', 'password' => Hash::make('segredo123')])`), logue e acesse `/completar-perfil`. Confirmar visualmente: troca de passo sem reload, os 15 avatares aparecem em círculo branco, tema claro/escuro funciona (`ThemeToggle`), e o submit final redireciona pra `/`.

- [ ] **Step 5: Pint (não se aplica a .vue, mas confirma que nada de PHP ficou sujo) e commit**

```bash
vendor/bin/pint --dirty
git add resources/js/components/AppInput.vue resources/js/pages/onboarding/Show.vue
git commit -m "Adiciona a página do wizard de onboarding pós-login"
```

---

### Task 7: Logout

**Files:**
- Create: `app/Http/Controllers/Auth/LogoutUserController.php`
- Modify: `routes/auth.php`
- Test: `tests/Feature/Http/LogoutUserControllerTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: rota nomeada `logout` (`POST logout`) — usada por qualquer UI futura que precise de um botão de sair (nenhuma task deste plano consome diretamente).

- [ ] **Step 1: Escrever o teste (falhando)**

Criar `tests/Feature/Http/LogoutUserControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('desloga o usuário e redireciona pro login', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

it('exige login pra deslogar', function () {
    $response = $this->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Feature/Http/LogoutUserControllerTest.php`
Expected: FAIL — rota `/logout` não existe (404) e/ou `route('logout')` não existe.

- [ ] **Step 3: Criar o controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutUserController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

- [ ] **Step 4: Registrar a rota**

Em `routes/auth.php`, adicionar o import e a rota dentro do grupo `auth` já existente (depois das rotas de onboarding da Task 5):

```php
use App\Http\Controllers\Auth\LogoutUserController;
```

```php
    Route::post('logout', LogoutUserController::class)->name('logout');
```

- [ ] **Step 5: Gerar tipos do Wayfinder, rodar e confirmar que passa**

Run: `php artisan wayfinder:generate --no-interaction && php artisan test tests/Feature/Http/LogoutUserControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Pint e commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Auth/LogoutUserController.php routes/auth.php resources/js/actions/App/Http/Controllers/Auth resources/js/routes tests/Feature/Http/LogoutUserControllerTest.php
git commit -m "Adiciona a rota e o controller de logout"
```

---

### Task 8: Guards de rota (`auth` + `onboarded`)

**Files:**
- Create: `app/Http/Middleware/EnsureProfileIsComplete.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Http/RouteGuardsTest.php`

**Interfaces:**
- Consumes: `User::hasCompletedProfile()` (Task 1), rota `onboarding.show` (Task 5).
- Produces: middleware alias `onboarded` — nenhuma outra task deste plano depende dele, mas futuras rotas "de dentro do jogo" (Tela de Início, abertura de pack) vão usar o mesmo grupo `['auth', 'onboarded']`.

- [ ] **Step 1: Escrever o teste (falhando)**

Criar `tests/Feature/Http/RouteGuardsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redireciona deslogado pro login ao acessar a home', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

it('redireciona pro onboarding quando o perfil está incompleto', function () {
    $user = User::factory()->withoutProfile()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('onboarding.show'));
});

it('deixa passar quem já completou o perfil', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
});
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Feature/Http/RouteGuardsTest.php`
Expected: FAIL — hoje `/` é pública, o primeiro teste falha (retorna 200 em vez de redirect).

- [ ] **Step 3: Criar o middleware**

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasCompletedProfile()) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Registrar o alias em `bootstrap/app.php`**

```php
<?php

use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'onboarded' => EnsureProfileIsComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
```

- [ ] **Step 5: Guardar a rota `/`**

Substituir `routes/web.php` por:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::inertia('/', 'Welcome')->name('home');
});

if (! app()->isProduction()) {
    Route::inertia('/design-system', 'DesignSystem')->name('design-system');
}

require __DIR__.'/auth.php';
```

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `php artisan test tests/Feature/Http/RouteGuardsTest.php`
Expected: PASS nos 3 testes.

- [ ] **Step 7: Pint e commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Middleware/EnsureProfileIsComplete.php bootstrap/app.php routes/web.php tests/Feature/Http/RouteGuardsTest.php
git commit -m "Adiciona os guards de rota: deslogado vai pro login, sem perfil vai pro onboarding"
```

---

### Task 9: Verificação final e usuário admin

**Files:**
- Nenhum arquivo de código — task operacional + verificação de regressão.

**Interfaces:**
- Consumes: tudo das Tasks 1-8.
- Produces: usuário admin utilizável em ambiente local.

- [ ] **Step 1: Rodar a suíte inteira**

Run: `php artisan test`
Expected: PASS em tudo. A Task 1 mudou o `UserFactory` pra sempre criar uma `Species` junto (`avatar_species_id => Species::factory()`) — se algum teste antigo que não foi tocado neste plano quebrar por causa disso, investigar e corrigir nesse teste especificamente (não deveria acontecer, mas é o ponto de maior alcance da mudança).

- [ ] **Step 2: Type-check do frontend**

Run: `npm run types:check`
Expected: sem erros.

- [ ] **Step 3: Pint final (sanity check, não deveria sobrar nada)**

Run: `vendor/bin/pint --dirty`
Expected: "no changes" ou já formatado — todas as tasks anteriores já rodaram isso.

- [ ] **Step 4: Migração limpa em ambiente local**

Run: `php artisan migrate:fresh --no-interaction`
Expected: roda sem erro, schema final com `avatar_species_id`, `nickname`/`tag`/`birthdate` nullable.

- [ ] **Step 5: Rodar o pokedex:sync de verdade (se ainda não rodou na Task 2)**

Run: `php artisan storage:link` (se ainda não existir o symlink) `&& php artisan pokedex:sync`
Expected: 151 espécies no banco local, sprites em `storage/app/public/species/`.

- [ ] **Step 6: Criar o usuário admin**

Run:

```bash
php artisan tinker --execute '
$user = App\Models\User::create([
    "email" => "ramondfernandes@gmail.com",
    "password" => "radofe15",
    "is_admin" => true,
    "email_verified_at" => now(),
    "client_seed" => Illuminate\Support\Str::random(32),
]);
echo "Admin criado, id=" . $user->id . PHP_EOL;
'
```

`password` sai em texto puro do comando só porque o cast `hashed` do model já transforma pra hash na gravação (confirmado em `User::casts()`, `'password' => 'hashed'`) — nunca fica em texto puro no banco. `nickname`/`tag`/`birthdate`/`avatar_species_id` ficam `null` de propósito: o próprio Ramon completa pelo wizard no primeiro login, testando o fluxo real em vez de ganhar tratamento especial.

- [ ] **Step 7: Verificação manual end-to-end**

Suba o dev server, acesse `/login`, entre com `ramondfernandes@gmail.com` / `radofe15`. Confirmar: cai automaticamente no wizard de onboarding (perfil incompleto), completa os 3 passos, cai na home (`Welcome`). Deslogar (`POST /logout` — sem UI ainda, mas dá pra confirmar via `php artisan tinker` que a sessão foi invalidada, ou via um teste manual com curl) e confirmar que acessar `/` de novo deslogado volta pro `/login`.
