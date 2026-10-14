# Fase 3, o social — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rankings de shinies/IV/primeiros, Cartão de Treinador (perfil público) e comparação de dois espécimes da mesma espécie.

**Architecture:** Tudo no servidor (Actions em `app/Actions`, controllers finos, Inertia). A parte A (rankings) é do Kimi numa worktree própria (`fase3-rankings`); B, C e D são do Claude no branch `fase3-perfil`. A parte D (links) só entra depois do merge de A e B.

**Tech Stack:** Laravel 12, Inertia v3 + Vue 3 (`<script setup>`), Tailwind 4, Pest, Wayfinder, PHPStan, Pint, `vp check`.

**Spec:** `docs/superpowers/specs/2026-10-14-fase-3-social-design.md`

## Global Constraints

- Ler `CLAUDE.md` e `SPEC.md` antes; valem todas as regras de lá.
- `declare(strict_types=1)` em todo PHP; Pint preset Laravel; enums nativos.
- Controller sem lógica: valida, chama uma Action, devolve Inertia/JSON.
- Nada de número mágico: constantes novas em `config/game.php`.
- Nenhum cálculo de resultado no cliente: quem ganha, posição, ordem, tudo vem pronto do servidor.
- Vocabulário: `Species` (espécie) e `Specimen` (espécime); nunca "card", "pokemon" solto, "instance".
- Espécime fechado nunca aparece: o `RevealedScope` global do `Specimen` já filtra; não usar `withoutGlobalScopes()` nestas telas.
- Telas novas: `defineOptions({ layout: [EntryLayout, GroupLayout] })`, moldura `GroupScreen`, tema claro com as cores fixas das telas do grupo, layout crítico em Tailwind.
- Sprite de espécie sempre por `FittedSprite`, nunca `<img>` solto; caminho `/storage/{path}`.
- Rotas no front sempre via Wayfinder (`@/routes/...`); depois de criar rota, `php artisan wayfinder:generate`.
- Commits em português, imperativo, sem prefixo.
- Verificação final de cada parte: `composer ci:check` verde.

## Review Focus

1. **Helper `specimenOf` redeclarado:** ele é função global em `tests/Feature/Http/GroupScreensTest.php`; declarar outra com o mesmo nome em outro arquivo quebra a suíte inteira. Teste novo usa `Specimen::factory()->for($user)->for(Opening::factory()->for($user))` direto ou um helper com nome próprio.
2. **Ranking com o grupo todo em zero** (banco zerado, como hoje): todas as abas têm de renderizar com todo mundo sem posição e o subtítulo de "como entrar". Teste em A1/A2.
3. **Cartão de quem não tem nenhum espécime:** time vazio some, coleção toda em silhueta, posição "—". Teste em B2.
4. **Comparar com um espécime que mudou de dono ou é do próprio jogador dos dois lados:** o `mine` tem de ser de quem vê; os dois serem seus é válido. Teste em C1.
5. **Abrir `/treinador/{id}` de quem não completou o perfil ou id inexistente:** 404, não erro 500. Teste em B2.

---

# Parte A — Rankings novos (Kimi, branch `fase3-rankings`)

### Task A1: Abas de ranking no servidor

**Files:**
- Create: `app/Enums/RankingTab.php`
- Create: `app/Services/RankPositions.php`
- Create: `app/Actions/Ranking/BuildShinyRanking.php`
- Create: `app/Actions/Ranking/BuildIvRanking.php`
- Create: `app/Actions/Ranking/BuildFirstMintRanking.php`
- Create: `app/Actions/Ranking/BuildRanking.php`
- Modify: `app/Http/Controllers/Ranking/ShowRankingController.php`
- Modify: `config/game.php` (bloco `ranking`)
- Test: `tests/Feature/Http/RankingTabsTest.php`

**Interfaces:**
- Consumes: `App\Actions\BuildSpeciesRanking::handle(): list<array{userId, nickname, tag, avatarSpritePath, ownedSpecies, shinyCount, position}>` (não mudar: a Home usa).
- Produces: `BuildRanking::handle(RankingTab $tab): list<RankingRow>` onde
  `RankingRow = array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, position: int|null, value: int, specimen: array{speciesName: string, spritePath: string, isShiny: bool, mintNumber: int}|null}`.
  Props da página `ranking/Show`: `rows: list<RankingRow>`, `viewerId: int`, `tab: string` (valor do enum).

- [ ] **Step 1: Escrever os testes que falham**

```php
<?php

declare(strict_types=1);

use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function rankedSpecimen(User $owner, array $attributes = []): Specimen
{
    return Specimen::factory()->for($owner)->for(Opening::factory()->for($owner))->create($attributes);
}

it('cai na aba de espécies sem query ou com aba inválida', function (string $url) {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get($url)->assertInertia(fn (Assert $page) => $page
        ->component('ranking/Show')
        ->where('tab', 'especies'));
})->with(['/ranking', '/ranking?por=xyz']);

it('a aba de espécies mantém a ordem de hoje e manda o valor', function () {
    [$a, $b] = Species::factory()->count(2)->create();
    $lin = User::factory()->create(['nickname' => 'Lin']);
    $bia = User::factory()->create(['nickname' => 'Bia']);
    rankedSpecimen($lin, ['species_id' => $a->id]);
    rankedSpecimen($lin, ['species_id' => $b->id]);
    rankedSpecimen($bia, ['species_id' => $a->id]);

    $this->actingAs($bia)->get('/ranking?por=especies')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.nickname', 'Lin')
        ->where('rows.0.value', 2)
        ->where('rows.0.position', 1)
        ->where('rows.1.value', 1)
        ->where('rows.1.specimen', null));
});

it('ordena por shinies, empate divide a posição e zero fica sem posição', function () {
    $species = Species::factory()->create();
    $a = User::factory()->create(['nickname' => 'Ana']);
    $b = User::factory()->create(['nickname' => 'Bia']);
    $c = User::factory()->create(['nickname' => 'Cau']);
    rankedSpecimen($a, ['species_id' => $species->id, 'is_shiny' => true]);
    rankedSpecimen($b, ['species_id' => $species->id, 'is_shiny' => true]);
    rankedSpecimen($c, ['species_id' => $species->id]);
    rankedSpecimen($a, ['species_id' => $species->id, 'is_shiny' => true])->forceFill(['revealed_at' => null])->save();

    $this->actingAs($c)->get('/ranking?por=shinies')->assertInertia(fn (Assert $page) => $page
        ->where('tab', 'shinies')
        ->where('rows.0.value', 1)
        ->where('rows.0.position', 1)
        ->where('rows.1.value', 1)
        ->where('rows.1.position', 1)
        ->where('rows.2.nickname', 'Cau')
        ->where('rows.2.position', null));
});

it('ordena pelo maior IV de um espécime só e mostra qual é', function () {
    $pikachu = Species::factory()->create(['name' => 'Pikachu']);
    $strong = User::factory()->create(['nickname' => 'Forte']);
    $weak = User::factory()->create(['nickname' => 'Fraco']);
    $empty = User::factory()->create(['nickname' => 'Vazio']);
    rankedSpecimen($strong, ['species_id' => $pikachu->id, 'iv_hp' => 31, 'iv_atk' => 31, 'iv_def' => 31, 'iv_spa' => 31, 'iv_spd' => 31, 'iv_spe' => 31, 'is_shiny' => true]);
    rankedSpecimen($strong, ['species_id' => $pikachu->id, 'iv_hp' => 0, 'iv_atk' => 0, 'iv_def' => 0, 'iv_spa' => 0, 'iv_spd' => 0, 'iv_spe' => 0]);
    rankedSpecimen($weak, ['species_id' => $pikachu->id, 'iv_hp' => 1, 'iv_atk' => 1, 'iv_def' => 1, 'iv_spa' => 1, 'iv_spd' => 1, 'iv_spe' => 1]);

    $this->actingAs($empty)->get('/ranking?por=iv')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.nickname', 'Forte')
        ->where('rows.0.value', 186)
        ->where('rows.0.specimen.speciesName', 'Pikachu')
        ->where('rows.0.specimen.isShiny', true)
        ->where('rows.0.specimen.spritePath', $pikachu->sprite_shiny_path)
        ->where('rows.1.value', 6)
        ->where('rows.1.position', 2)
        ->where('rows.2.nickname', 'Vazio')
        ->where('rows.2.position', null)
        ->where('rows.2.specimen', null));
});

it('conta os primeiros do servidor (mint 1)', function () {
    [$a, $b] = Species::factory()->count(2)->create();
    $first = User::factory()->create(['nickname' => 'Pioneira']);
    $late = User::factory()->create(['nickname' => 'Atrasado']);
    rankedSpecimen($first, ['species_id' => $a->id, 'mint_number' => 1]);
    rankedSpecimen($first, ['species_id' => $b->id, 'mint_number' => 1]);
    rankedSpecimen($late, ['species_id' => $a->id, 'mint_number' => 2]);

    $this->actingAs($late)->get('/ranking?por=primeiros')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.nickname', 'Pioneira')
        ->where('rows.0.value', 2)
        ->where('rows.0.position', 1)
        ->where('rows.1.nickname', 'Atrasado')
        ->where('rows.1.position', null));
});

it('com o grupo todo zerado, toda aba renderiza sem posição', function (string $tab) {
    User::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())->get("/ranking?por={$tab}")->assertInertia(fn (Assert $page) => $page
        ->has('rows', 3)
        ->where('rows.0.position', null));
})->with(['especies', 'shinies', 'iv', 'primeiros']);

it('não muda o que a Home recebe do ranking', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('summary.rankingTop')
        ->has('summary.rankingPosition'));
});
```

Antes de rodar, confira em `tests/Feature/Http/ShowHomeControllerTest.php` o caminho real das props da Home (`summary.rankingTop` ou outro) e ajuste o último teste a ele.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test tests/Feature/Http/RankingTabsTest.php`
Expected: FAIL (`tab` ausente, `value` ausente).

- [ ] **Step 3: Implementar**

`config/game.php`, novo bloco ao lado de `group`:

```php
    'ranking' => [
        // Mint que conta na aba "Primeiros": o primeiro espécime da espécie no servidor.
        'first_mint' => 1,
    ],
```

`app/Enums/RankingTab.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum RankingTab: string
{
    case Species = 'especies';
    case Shinies = 'shinies';
    case Iv = 'iv';
    case FirstMints = 'primeiros';
}
```

`app/Services/RankPositions.php` (a regra de posição compartilhada; se o PHPStan reclamar do tipo genérico, ajuste o docblock, nunca com `@phpstan-ignore`):

```php
<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Posição de ranking: as linhas já vêm ordenadas; mesmo `value` divide a
 * posição (1º, 1º, 3º) e `value` 0 fica sem posição (`null`).
 */
class RankPositions
{
    /**
     * @template T of array{value: int}
     *
     * @param  list<T>  $rows
     * @return list<T&array{position: int|null}>
     */
    public function assign(array $rows): array
    {
        $previous = null;
        $position = 0;

        foreach ($rows as $index => $row) {
            if ($row['value'] !== $previous) {
                $position = $index + 1;
                $previous = $row['value'];
            }

            $rows[$index]['position'] = $row['value'] > 0 ? $position : null;
        }

        return $rows;
    }
}
```

`app/Actions/Ranking/BuildShinyRanking.php` (mesma consulta do `BuildSpeciesRanking`, ordem trocada):

```php
<?php

declare(strict_types=1);

namespace App\Actions\Ranking;

use App\Models\Specimen;
use App\Models\User;
use App\Services\RankPositions;

/** Ranking por espécimes shiny; espécies distintas só desempatam a ordem. */
class BuildShinyRanking
{
    public function __construct(private readonly RankPositions $positions = new RankPositions) {}

    /**
     * @return list<array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, position: int|null, value: int, specimen: null}>
     */
    public function handle(): array
    {
        $users = User::query()
            ->whereNotNull('nickname')
            ->with('avatarSpecies:id,sprite_path,sprite_shiny_path')
            ->select('users.*')
            ->addSelect([
                'shiny_count' => Specimen::query()->selectRaw('count(*)')->whereColumn('user_id', 'users.id')->where('is_shiny', true),
                'owned_species' => Specimen::query()->selectRaw('count(distinct species_id)')->whereColumn('user_id', 'users.id'),
            ])
            ->orderByDesc('shiny_count')
            ->orderByDesc('owned_species')
            ->orderBy('id')
            ->get();

        return $this->positions->assign($users->map(fn (User $user): array => [
            'userId' => $user->id,
            'nickname' => (string) $user->nickname,
            'tag' => (string) $user->tag,
            'avatarSpritePath' => $user->avatarSpritePath(),
            'value' => (int) $user->getAttribute('shiny_count'),
            'specimen' => null,
        ])->values()->all());
    }
}
```

`app/Actions/Ranking/BuildFirstMintRanking.php`: igual ao de cima trocando `shiny_count` por

```php
'first_mints' => Specimen::query()->selectRaw('count(*)')->whereColumn('user_id', 'users.id')->where('mint_number', (int) config('game.ranking.first_mint')),
```

ordenado por `first_mints` desc, `owned_species` desc, `id`, com `value` = `first_mints`. Docblock: "Ranking por primeiros do servidor (mint 1 da espécie); espécies distintas só desempatam a ordem."

`app/Actions/Ranking/BuildIvRanking.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Ranking;

use App\Models\Specimen;
use App\Models\User;
use App\Services\RankPositions;

/**
 * Ranking pelo maior IV total de um espécime só. Empate no IV divide a
 * posição; o menor mint desse espécime desempata a ordem.
 */
class BuildIvRanking
{
    public function __construct(private readonly RankPositions $positions = new RankPositions) {}

    /**
     * @return list<array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, position: int|null, value: int, specimen: array{speciesName: string, spritePath: string, isShiny: bool, mintNumber: int}|null}>
     */
    public function handle(): array
    {
        $users = User::query()->whereNotNull('nickname')->with('avatarSpecies:id,sprite_path,sprite_shiny_path')->orderBy('id')->get();

        // O grupo é pequeno (só por convite): um espécime por dono, o melhor.
        $best = Specimen::query()
            ->whereIn('user_id', $users->modelKeys())
            ->with('species:id,name,sprite_path,sprite_shiny_path')
            ->orderByDesc('iv_total')
            ->orderBy('mint_number')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $rows = $users->map(function (User $user) use ($best): array {
            $specimen = $best->get($user->id);

            return [
                'userId' => $user->id,
                'nickname' => (string) $user->nickname,
                'tag' => (string) $user->tag,
                'avatarSpritePath' => $user->avatarSpritePath(),
                'value' => $specimen !== null ? (int) $specimen->iv_total : 0,
                'mint' => $specimen?->mint_number ?? PHP_INT_MAX,
                'specimen' => $specimen === null ? null : [
                    'speciesName' => $specimen->species->name,
                    'spritePath' => $specimen->is_shiny ? $specimen->species->sprite_shiny_path : $specimen->species->sprite_path,
                    'isShiny' => $specimen->is_shiny,
                    'mintNumber' => $specimen->mint_number,
                ],
            ];
        })->sort(fn (array $a, array $b): int => [$b['value'], $a['mint'], $a['userId']] <=> [$a['value'], $b['mint'], $b['userId']])
            ->map(function (array $row): array {
                unset($row['mint']);

                return $row;
            })
            ->values()
            ->all();

        return $this->positions->assign($rows);
    }
}
```

`app/Actions/Ranking/BuildRanking.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Ranking;

use App\Actions\BuildSpeciesRanking;
use App\Enums\RankingTab;

/** Escolhe o ranking pela aba; a de espécies vira o mesmo formato das outras. */
class BuildRanking
{
    public function __construct(
        private readonly BuildSpeciesRanking $species,
        private readonly BuildShinyRanking $shinies,
        private readonly BuildIvRanking $iv,
        private readonly BuildFirstMintRanking $firstMints,
    ) {}

    /**
     * @return list<array{userId: int, nickname: string, tag: string, avatarSpritePath: string|null, position: int|null, value: int, specimen: array{speciesName: string, spritePath: string, isShiny: bool, mintNumber: int}|null}>
     */
    public function handle(RankingTab $tab): array
    {
        return match ($tab) {
            RankingTab::Species => array_map(fn (array $row): array => [
                'userId' => $row['userId'],
                'nickname' => $row['nickname'],
                'tag' => $row['tag'],
                'avatarSpritePath' => $row['avatarSpritePath'],
                'position' => $row['position'],
                'value' => $row['ownedSpecies'],
                'specimen' => null,
            ], $this->species->handle()),
            RankingTab::Shinies => $this->shinies->handle(),
            RankingTab::Iv => $this->iv->handle(),
            RankingTab::FirstMints => $this->firstMints->handle(),
        };
    }
}
```

`ShowRankingController`:

```php
    public function __invoke(Request $request, BuildRanking $action): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $tab = RankingTab::tryFrom($request->string('por')->toString()) ?? RankingTab::Species;

        return Inertia::render('ranking/Show', ['rows' => $action->handle($tab), 'viewerId' => $user->id, 'tab' => $tab->value]);
    }
```

O teste antigo `ordena o ranking por espécies...` em `GroupScreensTest.php` checa `rows.1.shinyCount`: troque essa linha por `->where('rows.1.value', 1)` (a aba de espécies agora manda `value`; o `shinyCount` continua só na Home).

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test tests/Feature/Http/RankingTabsTest.php tests/Feature/Http/GroupScreensTest.php tests/Feature/Http/ShowHomeControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "Monta os rankings de shinies, maior IV e primeiros no servidor"
```

### Task A2: Abas na tela de Ranking

**Files:**
- Modify: `resources/js/pages/ranking/Show.vue`

**Interfaces:**
- Consumes: props `rows: RankingRow[]`, `viewerId: number`, `tab: 'especies' | 'shinies' | 'iv' | 'primeiros'` (Task A1); rota `show` de `@/routes/ranking` com `query: { por }`.

- [ ] **Step 1: Trocar o tipo `RankingRow` e as props**

```ts
type RankingTab = 'especies' | 'shinies' | 'iv' | 'primeiros';

type RankingRow = {
    userId: number;
    nickname: string;
    tag: string;
    avatarSpritePath: string | null;
    position: number | null;
    value: number;
    specimen: {
        speciesName: string;
        spritePath: string;
        isShiny: boolean;
        mintNumber: number;
    } | null;
};

const props = defineProps<{
    rows: RankingRow[];
    viewerId: number;
    tab: RankingTab;
}>();
```

- [ ] **Step 2: Abas como links (o servidor decide o conteúdo)**

```ts
import { Link } from '@inertiajs/vue3';
import { show as rankingShow } from '@/routes/ranking';

const TABS: { key: RankingTab; label: string; unit: [string, string] }[] = [
    { key: 'especies', label: 'Espécies', unit: ['espécie', 'espécies'] },
    { key: 'shinies', label: 'Shinies', unit: ['shiny', 'shinies'] },
    { key: 'iv', label: 'Maior IV', unit: ['IV', 'IV'] },
    { key: 'primeiros', label: 'Primeiros', unit: ['primeiro', 'primeiros'] },
];

const current = computed(() => TABS.find((tab) => tab.key === props.tab) ?? TABS[0]);
```

No template, logo dentro do `GroupScreen`, uma fileira de pílulas igual ao filtro da Coleção (`resources/js/pages/collection/Show.vue`, o bloco `v-for="option in FILTERS"`: copie as classes de lá), mas cada pílula é um `<Link :href="rankingShow({ query: { por: tab.key } })" preserve-scroll :aria-current="tab.key === props.tab ? 'page' : undefined">`. Em telas estreitas a fileira rola na horizontal (`overflow-x-auto`, sem quebrar linha).

- [ ] **Step 3: Subtítulo e valor da linha por aba**

```ts
const subtitle = computed(() => {
    if (viewerRow.value?.position) {
        return `Você está em ${viewerRow.value.position}º em ${current.value.label.toLowerCase()}`;
    }

    return props.tab === 'primeiros'
        ? 'Seja o primeiro a abrir uma espécie pra entrar'
        : 'Abra um pack pra entrar no ranking';
});

const valueLabel = (row: RankingRow) =>
    props.tab === 'iv'
        ? `IV ${row.value}`
        : `${row.value} ${row.value === 1 ? current.value.unit[0] : current.value.unit[1]}`;
```

Onde hoje a linha mostra a quantidade de espécies (`row.ownedSpecies`) e os shinies, mostre `valueLabel(row)`. Na aba `iv`, quando `row.specimen` existe, mostre à direita um `FittedSprite` 40px (`:src="`/storage/${row.specimen.spritePath}`"`) com o nome da espécie embaixo em texto pequeno (e "shiny" em `CYAN` de `@/lib/pack/meta` se `isShiny`). O resto da linha (posição, avatar, destaque do 1º, linha de quem vê) fica como está.

- [ ] **Step 4: Checar**

Run: `npm run types:check && npx vp check --fix`
Expected: sem erros.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "Põe as abas de shinies, maior IV e primeiros na tela de Ranking"
```

### Task A3: Fechamento do Kimi

- [ ] Rodar `composer ci:check` e deixar verde.
- [ ] Relatório curto (o que fez, arquivos, como testou, decisões e dúvidas).

---

# Parte B — Cartão de Treinador (Claude, branch `fase3-perfil`)

### Task B1: `FindTopSpecimens`

**Files:**
- Create: `app/Actions/FindTopSpecimens.php`
- Modify: `app/Actions/FindBestSpecimen.php` (passa a usar a nova)
- Modify: `config/game.php` (`profile.team_size` => 6)
- Test: `tests/Feature/Actions/FindTopSpecimensTest.php`

**Interfaces:**
- Produces: `FindTopSpecimens::handle(User $user, int $limit): list<array{id: int, speciesId: int, speciesName: string, spritePath: string, isShiny: bool, rarityTier: string, mintNumber: int, ivTotal: int}>`, ordem shiny > raridade > IV > menor mint.
- `FindBestSpecimen::handle(User): ?array` continua com o mesmo retorno de hoje.

- [ ] **Step 1: Teste que falha**

```php
it('devolve os melhores na ordem shiny, raridade, IV e menor mint, com limite', function () {
    $user = User::factory()->create();
    $common = Species::factory()->create();
    $legend = Species::factory()->legendary()->create();
    $make = fn (array $attributes) => Specimen::factory()->for($user)->for(Opening::factory()->for($user))->create($attributes);
    $commonShiny = $make(['species_id' => $common->id, 'is_shiny' => true]);
    $legendLowIv = $make(['species_id' => $legend->id, 'iv_hp' => 0, 'iv_atk' => 0, 'iv_def' => 0, 'iv_spa' => 0, 'iv_spd' => 0, 'iv_spe' => 0]);
    $legendHighIv = $make(['species_id' => $legend->id, 'iv_hp' => 31]);
    $make(['species_id' => $common->id]);

    $top = (new FindTopSpecimens)->handle($user, 3);

    expect(array_column($top, 'id'))->toBe([$commonShiny->id, $legendHighIv->id, $legendLowIv->id]);
});

it('ignora espécime fechado e devolve vazio sem espécimes', function () {
    $user = User::factory()->create();
    Specimen::factory()->for($user)->for(Opening::factory()->for($user))->unrevealed()->create();

    expect((new FindTopSpecimens)->handle($user, 6))->toBe([]);
});
```

- [ ] **Step 2:** `php artisan test tests/Feature/Actions/FindTopSpecimensTest.php` → FAIL.
- [ ] **Step 3:** Mover a ordenação do `FindBestSpecimen` (constante `TIER_RANK` e o `sort`) pra `FindTopSpecimens`, com `->take($limit)` e o mapa pro formato acima; `FindBestSpecimen::handle` vira `($this->top->handle($user, 1)[0] ?? null)` mapeado pras chaves que já devolve hoje.
- [ ] **Step 4:** `php artisan test tests/Feature/Actions/FindTopSpecimensTest.php tests/Feature/Http/ShowHomeControllerTest.php` → PASS.
- [ ] **Step 5:** Commit "Separa os melhores espécimes do jogador numa Action com limite".

### Task B2: Cartão no servidor

**Files:**
- Create: `app/Actions/Trainer/BuildTrainerCard.php`
- Create: `app/Http/Controllers/Trainer/ShowTrainerController.php`
- Create: `app/Http/Controllers/Trainer/ShowTrainerSpeciesController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Http/TrainerCardTest.php`

**Interfaces:**
- Consumes: `FindTopSpecimens` (B1), `BuildCollection::handle(User)`, `BuildSpeciesRanking::handle()`, `BuildSpeciesSpecimens::handle(User, Species, Request)`, `Friendship::between()`.
- Produces: rotas `trainer.show` (`GET treinador/{user}`) e `trainer.species` (`GET treinador/{user}/colecao/{species}`, JSON `{specimens, viewerSpecimenId}`). Props de `trainer/Show`:
  `trainer {id, nickname, tag, avatarSpritePath, memberSince, isViewer}`, `stats {rankingPosition, shinyCount, openedPacks}`, `friendship {state: 'self'|'none'|'outgoing'|'incoming'|'friends', friendshipId: int|null}`, `team` (formato do B1), `collection` (formato do `BuildCollection`).

- [ ] **Step 1: Testes que falham** (`tests/Feature/Http/TrainerCardTest.php`):
  - 404 pra `User::factory()->withoutProfile()` e pra id inexistente; redireciona pro login sem auth.
  - números: com 2 espécies, 1 shiny e 2 `Opening`s, `stats.shinyCount` = 1, `stats.openedPacks` = 2, `stats.rankingPosition` = 1, `collection.ownedSpecies` = 2.
  - `memberSince` = `'set/2026'` com `created_at` em 2026-09-15.
  - amizade: `self` no próprio cartão; `none`; `outgoing` (viewer mandou, pendente); `incoming` com o `friendshipId`; `friends`.
  - time com no máximo `game.profile.team_size` e sem espécime fechado; jogador sem nada tem `team` vazio e `stats.rankingPosition` nulo.
  - `GET /treinador/{id}/colecao/{species}` só traz espécimes do dono e `viewerSpecimenId` = melhor espécime de quem vê daquela espécie (shiny, depois IV) ou `null`.
- [ ] **Step 2:** rodar → FAIL.
- [ ] **Step 3:** implementar `BuildTrainerCard::handle(User $owner, User $viewer): array` e os dois controllers finos (`abort_unless($user->hasCompletedProfile(), 404)` como o `PackController` já faz). `memberSince`: `$owner->created_at->setTimezone(config('game.display_timezone'))->locale('pt_BR')->translatedFormat('M/Y')` em minúsculas. `viewerSpecimenId`: primeiro id de `BuildSpeciesSpecimens::handle($viewer, $species, $request)`. Rotas dentro do grupo `auth` + `onboarded`, e `php artisan wayfinder:generate`.
- [ ] **Step 4:** rodar → PASS.
- [ ] **Step 5:** Commit "Monta o Cartão de Treinador no servidor".

### Task B3: Tela do cartão

**Files:**
- Create: `resources/js/composables/useSpecimenSheet.ts` (busca e navegação da ficha, tirada de `collection/Show.vue`)
- Create: `resources/js/components/collection/PokedexGrid.vue` (filtro Todas/Tenho/Faltam + grade de `CollectionCard`, tirados de `collection/Show.vue`)
- Modify: `resources/js/pages/collection/Show.vue` (passa a usar os dois)
- Create: `resources/js/pages/trainer/Show.vue`
- Modify: `resources/js/components/pack/SpecimenSheet.vue` (slot `actions` opcional embaixo da ficha)
- Modify: `resources/js/layouts/GroupLayout.vue` (`'trainer/Show': { order: 9 }`)

**Interfaces:**
- `useSpecimenSheet(urlFor: (speciesId: number) => string)` → `{ specimens, index, current, loadingId, extra, open(speciesId), step(delta), close() }`, onde `extra` guarda o resto do JSON (ex.: `viewerSpecimenId`).
- `PokedexGrid` props `{ species: SpeciesEntry[], loadingId: number | null }`, emit `open(speciesId)`.

- [ ] **Step 1:** extrair o composable e a grade, trocar a Coleção por eles, `npm run types:check` e conferir a Coleção no navegador (igual a antes).
- [ ] **Step 2:** `trainer/Show.vue`, de cima pra baixo: cartão (`TrainerAvatar`, `nickname#tag`, "Treinador desde {memberSince}", três números com "—" quando nulo), barra "X de 151", botão de amizade (`router.post` em `@/routes/friends` `requests.store` com `handle: 'nick#tag'`, ou `accept` com o `friendshipId`; selo "Amigos"; nada no próprio cartão, que mostra "Este é o seu cartão" com link pro `/perfil`), "Time" (até 6 cartas com `rarityLook`; some se vazio; toque abre a ficha), `PokedexGrid`. A ficha usa o slot `actions` pra um botão "Comparar com o meu" quando `extra.viewerSpecimenId` não é nulo e o cartão não é seu (o botão entra só na C2; até lá o slot fica vazio).
- [ ] **Step 3:** `composer ci:check`; conferir no navegador a 390px e 1280px com a conta de teste.
- [ ] **Step 4:** Commit "Cria a tela do Cartão de Treinador".

---

# Parte C — Comparar (Claude, branch `fase3-perfil`)

### Task C1: Comparação no servidor

**Files:**
- Create: `app/Actions/Trainer/CompareSpecimens.php`
- Create: `app/Actions/Trainer/BuildComparison.php`
- Create: `app/Http/Controllers/Trainer/CompareSpecimensController.php`
- Modify: `routes/web.php` (`GET comparar/{mine}/{theirs}`, nome `compare.show`)
- Test: `tests/Unit/Actions/CompareSpecimensTest.php`, `tests/Feature/Http/CompareSpecimensTest.php`

**Interfaces:**
- `CompareSpecimens::handle(Specimen $mine, Specimen $theirs): array{stats: list<array{key: 'ivTotal'|'ivHp'|'ivAtk'|'ivDef'|'ivSpa'|'ivSpd'|'ivSpe', mine: int, theirs: int, winner: 'mine'|'theirs'|'tie'}>, mint: array{mine: int, theirs: int, winner: 'mine'|'theirs'|'tie'}, info: list<array{key: 'size'|'nature', mine: string, theirs: string}>, score: array{mine: int, theirs: int, total: int}}`. Pura: só lê os dois espécimes. Maior ganha nos stats; menor ganha no mint; `score` conta os 7 stats.
- `BuildComparison::handle(User $viewer, Specimen $mine, Specimen $theirs, Request $request): array{mine: PackSpecimen, theirs: PackSpecimen, mineOwner: {nickname, tag}, theirsOwner: {id, nickname, tag}, comparison: (acima), choices: list<array{id: int, mintNumber: int, isShiny: bool, ivTotal: int, isCurrent: bool}>}`. Lança `ModelNotFoundException` se `mine` não é de quem vê ou as espécies diferem. Os espécimes vêm pelo `PackSpecimenResource`.

- [ ] **Step 1:** testes: unit dos vencedores (incluindo empate e mint menor ganhando) e do placar; feature dos 404 (espécie diferente, `mine` de outro, espécime fechado, id inexistente), dos dois serem seus (200) e dos `choices` (só os de quem vê, daquela espécie, `isCurrent` certo).
- [ ] **Step 2:** rodar → FAIL.
- [ ] **Step 3:** implementar; `php artisan wayfinder:generate`.
- [ ] **Step 4:** rodar → PASS.
- [ ] **Step 5:** Commit "Compara dois espécimes da mesma espécie no servidor".

### Task C2: Tela de comparar

**Files:**
- Create: `resources/js/pages/compare/Show.vue`
- Modify: `resources/js/pages/trainer/Show.vue` (botão "Comparar com o meu" no slot `actions`)
- Modify: `resources/js/layouts/GroupLayout.vue` (`'compare/Show': { order: 10 }`)

- [ ] **Step 1:** tela: no topo os dois espécimes (sprite em `FittedSprite`, moldura `rarityLook`, dono, mint); linhas de stats com rótulos de `STATS` (+ "IV total"), o lado com `winner` em verde com setinha, empate neutro; linha do mint (o menor em verde); linhas de tamanho e natureza sem vencedor; placar "Seu {espécie} ganha em X de 7" (ou "Empate"); fileira de chips com `choices` (cada um é `Link` pra `compare.show` com o outro `mine`). A seta do `GroupScreen` volta pro `trainer.show` do `theirsOwner.id`.
- [ ] **Step 2:** no cartão, o slot `actions` da ficha ganha `Link` "Comparar com o meu" pra `compare.show(viewerSpecimenId, specimenAtual.id)`.
- [ ] **Step 3:** `composer ci:check`; navegador a 390px e 1280px.
- [ ] **Step 4:** Commit "Cria a tela de comparar espécimes".

---

# Parte D — Costura (Claude, depois do merge de A e B/C no `main`)

### Task D1: Links pro cartão

**Files:**
- Modify: `resources/js/pages/ranking/Show.vue` (linha vira `Link` pro `trainer.show(row.userId)`)
- Modify: `resources/js/pages/friends/Index.vue` (amigo vira link; `userId` já vem no `BuildFriendList`)
- Modify: `app/Actions/BuildGroupFeed.php` + `resources/js/pages/feed/Show.vue` (mandar `trainerId` e linkar)
- Modify: `resources/js/pages/profile/Edit.vue` (link "Ver meu Cartão de Treinador")
- Test: ajustar `tests/Feature/Http/GroupScreensTest.php` (feed tem `trainerId`)

- [ ] **Step 1:** teste do `trainerId` no feed → FAIL; implementar → PASS.
- [ ] **Step 2:** links nas quatro telas.
- [ ] **Step 3:** `composer ci:check`; navegador.
- [ ] **Step 4:** Commit "Liga ranking, amigos, feed e perfil ao Cartão de Treinador".
- [ ] **Step 5:** atualizar o checkpoint do `CLAUDE.md` e marcar a fase 3 no `SPEC.md`.
