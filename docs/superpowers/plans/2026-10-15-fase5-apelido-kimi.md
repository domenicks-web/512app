# Fase 5, apelido no espécime: backend (Kimi)

> Plano só com a parte do Kimi. Front, giroscópio e onboarding são do
> Claude. Rodar só os testes da task no meio (`php artisan test --compact
> <arquivo>`) e o `composer ci:check` **uma vez, no fim**. Não ler
> `docs/checkpoint.md`. Commits em português, imperativo, sem prefixo,
> terminando com `Co-Authored-By: Kimi K3 <noreply@moonshot.ai>`.

**Objetivo:** cada dono pode dar um apelido ao espécime, uma vez só. Na
troca e na libertação de banido, o apelido vai pro histórico e o espécime
fica sem apelido.

**Spec:** `docs/superpowers/specs/2026-10-15-fase5-design.md`, seção 1.

## Restrições globais

- `declare(strict_types=1)`, Pint com preset Laravel, PHPStan sem erro.
- Controller fino: valida com FormRequest e delega pra Action.
- Nada de número mágico: os limites ficam em `config/game.php`.
- Vocabulário: `Specimen`/espécime. Nunca "card", "pokemon" solto ou
  "instance".
- Migration sempre com `down()`.
- `specimens.nickname` (string 16, nullable) **já existe**. Não criar.
- Apelido "travado" = `specimens.nickname` não nulo. Não existe remover.

## Foco da revisão

1. Dois POST simultâneos do dono: só um grava (por isso o `lockForUpdate`).
2. Espécime de outro jogador, com o id na URL: 403/erro, nunca grava.
3. `"   "` (só espaços): recusado. O `TrimStrings` do Laravel apara antes.
4. Apelido com emoji/acento: conta caractere, não byte (`max` do Laravel
   já usa `mb_strlen`).
5. Histórico de um espécime trocado duas vezes: cada dono com o próprio
   apelido, na ordem certa.

---

### Task 1: config, migration e `NameSpecimen`

**Arquivos:**
- Modificar: `config/game.php`
- Criar: `database/migrations/2026_10_15_150000_add_nickname_to_specimen_transfers_table.php`
- Criar: `app/Exceptions/SpecimenNamingNotAllowedException.php`
- Criar: `app/Actions/NameSpecimen.php`
- Modificar: `app/Models/SpecimenTransfer.php`: acrescentar `'nickname'` no
  atributo `#[Fillable([...])]` da linha 25
- Teste: `tests/Feature/Actions/NameSpecimenTest.php`

**Produz:** `NameSpecimen::handle(Specimen $specimen, User $owner, string $nickname): Specimen`
e `SpecimenNamingNotAllowedException::{notOwner(), alreadyNamed()}`.

- [ ] **Passo 1: config.** Em `config/game.php`, criar a chave
  `specimen` (se não existir; se existir, acrescentar), com comentário
  no padrão do arquivo:

```php
    /*
    |--------------------------------------------------------------------------
    | Espécime
    |--------------------------------------------------------------------------
    |
    | `nickname_*`: tamanho do apelido que o dono dá ao espécime (uma vez
    | por dono; na troca ele vai pro histórico). O máximo bate com a
    | coluna `specimens.nickname`.
    |
    */

    'specimen' => [
        'nickname_min_length' => 1,
        'nickname_max_length' => 16,
    ],
```

- [ ] **Passo 2: migration.**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** O apelido que o dono anterior (`from_user_id`) tinha dado. */
    public function up(): void
    {
        Schema::table('specimen_transfers', function (Blueprint $table): void {
            $table->string('nickname', 16)->nullable()->after('trade_id');
        });
    }

    public function down(): void
    {
        Schema::table('specimen_transfers', function (Blueprint $table): void {
            $table->dropColumn('nickname');
        });
    }
};
```

- [ ] **Passo 3: exceção.**

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/** Quem pode dar apelido ao espécime (mensagem pro jogador). */
class SpecimenNamingNotAllowedException extends DomainException
{
    public static function notOwner(): self
    {
        return new self('Esse espécime não é seu.');
    }

    public static function alreadyNamed(): self
    {
        return new self('Esse espécime já tem apelido.');
    }
}
```

- [ ] **Passo 4: teste que falha** (`tests/Feature/Actions/NameSpecimenTest.php`).
  Os helpers `owned()`, `friends()` e `propose()` vêm de
  `tests/Feature/Actions/Trades/helpers.php`. O `Pest.php` não carrega:
  todo arquivo de teste novo deste plano começa com
  `require_once __DIR__.'/<caminho relativo>/Actions/Trades/helpers.php';`,
  como em `tests/Feature/Http/TradesTest.php`.

```php
<?php

declare(strict_types=1);

use App\Actions\NameSpecimen;
use App\Exceptions\SpecimenNamingNotAllowedException;
use App\Models\User;

it('grava o apelido que o dono dá', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner);

    app(NameSpecimen::class)->handle($specimen, $owner, 'João');

    expect($specimen->fresh()->nickname)->toBe('João');
});

it('recusa quem não é o dono', function () {
    $specimen = owned(User::factory()->create());

    app(NameSpecimen::class)->handle($specimen, User::factory()->create(), 'João');
})->throws(SpecimenNamingNotAllowedException::class, 'Esse espécime não é seu.');

it('não deixa dar um segundo apelido', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner, ['nickname' => 'João']);

    app(NameSpecimen::class)->handle($specimen, $owner, 'Raio');
})->throws(SpecimenNamingNotAllowedException::class, 'Esse espécime já tem apelido.');

it('confere a trava na linha do banco, não no objeto em memória', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner);
    $stale = $specimen->replicate()->setRawAttributes($specimen->getAttributes(), true);
    $specimen->forceFill(['nickname' => 'João'])->save();

    app(NameSpecimen::class)->handle($stale, $owner, 'Raio');
})->throws(SpecimenNamingNotAllowedException::class);
```

- [ ] **Passo 5:** `php artisan test --compact tests/Feature/Actions/NameSpecimenTest.php` → falha.

- [ ] **Passo 6: Action.**

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\SpecimenNamingNotAllowedException;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * O dono dá o apelido ao espécime, uma vez só: depois de gravado fica
 * travado até o espécime mudar de dono (a troca e a libertação limpam).
 * Lê de novo com lock, pra dois toques rápidos não gravarem dois apelidos.
 */
class NameSpecimen
{
    public function handle(Specimen $specimen, User $owner, string $nickname): Specimen
    {
        return DB::transaction(function () use ($specimen, $owner, $nickname): Specimen {
            $locked = Specimen::query()->whereKey($specimen->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== $owner->id) {
                throw SpecimenNamingNotAllowedException::notOwner();
            }

            if ($locked->nickname !== null) {
                throw SpecimenNamingNotAllowedException::alreadyNamed();
            }

            $locked->forceFill(['nickname' => $nickname])->save();

            return $locked;
        });
    }
}
```

  (Se o `Specimen` tiver global scope que esconda o espécime, por exemplo
  o `RevealedScope`, manter: só espécime revelado pode ganhar apelido.)

- [ ] **Passo 7:** rodar o teste → passa. Commit: "Deixa o dono dar apelido ao espécime, uma vez só".

### Task 2: rota, request e controller

**Arquivos:**
- Criar: `app/Http/Requests/NameSpecimenRequest.php`
- Criar: `app/Http/Controllers/Specimen/NameSpecimenController.php`
- Modificar: `routes/web.php` (junto do `especime/{specimen}` da linha ~51)
- Teste: `tests/Feature/Http/NameSpecimenTest.php`

**Produz:** rota `POST /especime/{specimen}/apelido`, nome
`specimens.nickname`. Sucesso: `back()`. Recusa da Action ou validação:
`back()->withErrors(['nickname' => ...])`.

- [ ] **Passo 1: teste que falha.**

```php
<?php

declare(strict_types=1);

use App\Models\User;

it('dá o apelido pelo POST', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner);

    $this->actingAs($owner)->post("/especime/{$specimen->id}/apelido", ['nickname' => '  João  '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($specimen->fresh()->nickname)->toBe('João');
});

it('recusa vazio, só espaços e mais de 16 caracteres', function (string $nickname) {
    $owner = User::factory()->create();
    $specimen = owned($owner);

    $this->actingAs($owner)->post("/especime/{$specimen->id}/apelido", ['nickname' => $nickname])
        ->assertSessionHasErrors('nickname');

    expect($specimen->fresh()->nickname)->toBeNull();
})->with(['', '   ', str_repeat('a', 17)]);

it('aceita 16 caracteres com acento e emoji', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner);

    $this->actingAs($owner)->post("/especime/{$specimen->id}/apelido", ['nickname' => 'Ãçéíõú⚡🔥aaaaaaa'])
        ->assertSessionHasNoErrors();

    expect($specimen->fresh()->nickname)->toBe('Ãçéíõú⚡🔥aaaaaaa');
});

it('devolve o erro da regra no campo nickname', function () {
    $specimen = owned(User::factory()->create());

    $this->actingAs(User::factory()->create())->post("/especime/{$specimen->id}/apelido", ['nickname' => 'João'])
        ->assertSessionHasErrors(['nickname' => 'Esse espécime não é seu.']);

    expect($specimen->fresh()->nickname)->toBeNull();
});
```

- [ ] **Passo 2:** rodar → falha.

- [ ] **Passo 3: request**, no molde do `ChangeNicknameRequest`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NameSpecimenRequest extends FormRequest
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
            'nickname' => [
                'required',
                'string',
                'min:'.config('game.specimen.nickname_min_length'),
                'max:'.config('game.specimen.nickname_max_length'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required' => 'Escolha um apelido.',
            'nickname.min' => 'O apelido precisa de pelo menos :min letra.',
            'nickname.max' => 'O apelido pode ter no máximo :max letras.',
        ];
    }

    public function nickname(): string
    {
        return $this->string('nickname')->trim()->toString();
    }
}
```

- [ ] **Passo 4: controller.**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specimen;

use App\Actions\NameSpecimen;
use App\Exceptions\SpecimenNamingNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\NameSpecimenRequest;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class NameSpecimenController extends Controller
{
    public function __invoke(NameSpecimenRequest $request, Specimen $specimen, NameSpecimen $action): RedirectResponse
    {
        /** @var User $owner */
        $owner = $request->user();

        try {
            $action->handle($specimen, $owner, $request->nickname());
        } catch (SpecimenNamingNotAllowedException $exception) {
            return back()->withErrors(['nickname' => $exception->getMessage()]);
        }

        return back();
    }
}
```

- [ ] **Passo 5: rota**, ao lado da `especime/{specimen}`:

```php
    Route::post('especime/{specimen}/apelido', NameSpecimenController::class)->whereNumber('specimen')->name('specimens.nickname');
```

  Depois: `php artisan wayfinder:generate` (o front usa a função gerada).

- [ ] **Passo 6:** rodar o teste → passa. Commit: "Abre a rota pra dar apelido ao espécime".

### Task 3: troca e libertação mandam o apelido pro histórico

**Arquivos:**
- Modificar: `app/Actions/Trades/AcceptTrade.php` (o loop do
  `SpecimenTransfer::create`, linhas ~62–72)
- Modificar: `app/Actions/Admin/BanPlayer.php` (`release()`, linhas ~60–75)
- Teste: `tests/Feature/Actions/Trades/TradeNicknameTest.php` e um caso
  novo no teste existente do `BanPlayer` (em `tests/Feature/Actions/Admin/`)

- [ ] **Passo 1: testes que falham.**

```php
<?php

declare(strict_types=1);

use App\Actions\NameSpecimen;
use App\Actions\Trades\AcceptTrade;
use App\Models\SpecimenTransfer;

it('na troca o apelido vai pro histórico e o novo dono pode dar outro', function () {
    [$a, $b] = friends();
    $mine = owned($a, ['nickname' => 'João']);
    $theirs = owned($b);
    app(AcceptTrade::class)->handle(propose($a, $b, [$mine->id], [$theirs->id]), $b);

    expect($mine->fresh()->nickname)->toBeNull()
        ->and(SpecimenTransfer::query()->where('specimen_id', $mine->id)->value('nickname'))->toBe('João')
        ->and(SpecimenTransfer::query()->where('specimen_id', $theirs->id)->value('nickname'))->toBeNull();

    app(NameSpecimen::class)->handle($mine->fresh(), $b, 'Raio');
    expect($mine->fresh()->nickname)->toBe('Raio');
});
```

  No teste do banimento, acrescentar um caso: um espécime do banido com
  `nickname => 'João'`. Depois do `BanPlayer`, o espécime está com o
  sistema, `nickname` nulo, e o `SpecimenTransfer` dele tem
  `nickname = 'João'`. Usar a mesma montagem que os casos de libertação
  que já existem no arquivo.

- [ ] **Passo 2:** rodar → falha.

- [ ] **Passo 3: `AcceptTrade`.** No loop:

```php
                $specimen = $specimens->get($item->specimen_id);
                SpecimenTransfer::create([
                    'specimen_id' => $item->specimen_id,
                    'from_user_id' => $from,
                    'to_user_id' => $to,
                    'trade_id' => $locked->id,
                    'nickname' => $specimen?->nickname,
                    'transferred_at' => now(),
                ]);
                $specimen?->forceFill(['user_id' => $to, 'nickname' => null])->save();
```

  (O `create` vem antes do `forceFill`, pra ler o apelido antes de
  limpar.)

- [ ] **Passo 4: `BanPlayer::release`.**

```php
        $specimens = Specimen::query()->withoutGlobalScopes()->where('user_id', $target->id)->lockForUpdate()->get(['id', 'nickname']);

        SpecimenTransfer::query()->insert($specimens->map(fn (Specimen $specimen): array => [
            'specimen_id' => $specimen->id,
            'from_user_id' => $target->id,
            'to_user_id' => $system->id,
            'trade_id' => null,
            'nickname' => $specimen->nickname,
            'transferred_at' => now(),
        ])->all());
        Specimen::query()->withoutGlobalScopes()->whereKey($specimens->modelKeys())->update(['user_id' => $system->id, 'nickname' => null]);
```

- [ ] **Passo 5:** rodar os dois testes e o arquivo de testes do
  `BanPlayer` inteiro → passam. Commit: "Leva o apelido pro histórico na troca e na libertação".

### Task 4: o apelido nos payloads

**Arquivos:**
- Modificar: `app/Http/Resources/PackSpecimenResource.php` (`'nickname' => $this->nickname`)
- Modificar: `app/Actions/PresentSpecimenSheet.php`
- Modificar: `app/Actions/BuildGroupFeed.php` (aberturas: `'nickname'`;
  trocas: o apelido do espécime destacado, se existir)
- Modificar: os payloads das trocas e do comparar que mandam espécime pro
  front. Achar com `grep -rn "mint_number" app/Actions/Trades app/Actions app/Http/Controllers/Compare* app/Http/Resources`.
  Onde houver um array de espécime com `name`/`speciesName` e mint,
  acrescentar `'nickname' => $specimen->nickname`.
- Teste: `tests/Feature/Actions/PresentSpecimenSheetNicknameTest.php`

**Produz (o front consome):**
- No espécime da ficha: `nickname: string|null` e `canName: bool`.
- Em cada `history[i]`: `specimenNickname: string|null`, o apelido que
  aquele dono deu.
- No feed (aberturas) e nos espécimes das trocas e do comparar:
  `nickname: string|null`.

- [ ] **Passo 1: teste que falha.**

```php
<?php

declare(strict_types=1);

use App\Actions\PresentSpecimenSheet;
use App\Actions\Trades\AcceptTrade;
use App\Models\Specimen;
use Illuminate\Http\Request;

it('mostra o apelido de cada dono no histórico e se quem vê pode dar o seu', function () {
    [$a, $b] = friends();
    $specimen = owned($a, ['nickname' => 'João']);
    app(AcceptTrade::class)->handle(propose($a, $b, [$specimen->id], [owned($b)->id]), $b);
    $specimen->fresh()->forceFill(['nickname' => 'Raio'])->save();
    app(AcceptTrade::class)->handle(propose($b, $a, [$specimen->id], [owned($a)->id]), $a);

    $sheet = fn ($viewer) => app(PresentSpecimenSheet::class)->handle(
        Specimen::query()->with(PresentSpecimenSheet::RELATIONS)->findOrFail($specimen->id),
        Request::create('/')->setUserResolver(fn () => $viewer),
    );

    $forA = $sheet($a);
    expect(array_column($forA['history'], 'specimenNickname'))->toBe(['João', 'Raio', null])
        ->and($forA['nickname'])->toBeNull()
        ->and($forA['canName'])->toBeTrue()
        ->and($sheet($b)['canName'])->toBeFalse();
});

it('não deixa o dono dar apelido de novo depois de dar', function () {
    [$a] = friends();
    $specimen = owned($a, ['nickname' => 'João']);

    $sheet = app(PresentSpecimenSheet::class)->handle(
        Specimen::query()->with(PresentSpecimenSheet::RELATIONS)->findOrFail($specimen->id),
        Request::create('/')->setUserResolver(fn () => $a),
    );

    expect($sheet['canName'])->toBeFalse()
        ->and($sheet['history'][0]['specimenNickname'])->toBe('João');
});
```

- [ ] **Passo 2:** rodar → falha.

- [ ] **Passo 3: `PresentSpecimenSheet`.** O apelido do dono *i* é o
  `nickname` do transfer que tirou o espécime dele (o transfer *i*,
  contando a captura como dono 0) ou, pro dono atual, o apelido atual:

```php
    public function handle(Specimen $specimen, Request $request): array
    {
        $transfers = $specimen->transfers->values();
        // Apelido que cada dono deu, na ordem: dono 0 (captura) até o atual.
        $nicknames = [...$transfers->pluck('nickname')->all(), $specimen->nickname];

        return [
            ...(new PackSpecimenResource($specimen))->toArray($request),
            'canName' => $request->user()?->id === $specimen->user_id && $specimen->nickname === null,
            'history' => [
                [
                    'kind' => 'caught',
                    'trainerId' => $specimen->opening->user->id,
                    'nickname' => (string) $specimen->opening->user->nickname,
                    'fromNickname' => null,
                    'specimenNickname' => $nicknames[0],
                    'at' => $specimen->caught_at->toIso8601String(),
                ],
                ...$transfers->map(fn (SpecimenTransfer $transfer, int $index): array => [
                    'kind' => $transfer->trade_id === null ? 'released' : 'traded',
                    'trainerId' => $transfer->toUser->id,
                    'nickname' => (string) $transfer->toUser->nickname,
                    'fromNickname' => (string) $transfer->fromUser->nickname,
                    'specimenNickname' => $nicknames[$index + 1],
                    'at' => $transfer->transferred_at->toIso8601String(),
                ])->all(),
            ],
        ];
    }
```

  Atenção: `history[i].nickname` já existe e é o **nickname do
  treinador**. Não mexer; o do espécime é `specimenNickname`.

- [ ] **Passo 4:** `PackSpecimenResource`: `'nickname' => $this->nickname,`
  logo depois de `'name'`. Feed, trocas e comparar: o campo `nickname`
  como descrito acima.

- [ ] **Passo 5:** rodar o teste novo e os de `tests/Feature/Http/GroupScreensTest.php`,
  `TradeHistoryTest.php` e do comparar → passam. Commit: "Manda o apelido do espécime pra ficha, trocas, feed e comparar".

### Fim

- [ ] `composer ci:check` uma vez. Se o Pint reclamar, `./vendor/bin/pint`
  e commit "Formata". Responder com a lista de commits e, por task, o
  que ficou diferente do plano, se algo ficou.
