# Fase 4C, suspensão e banimento: plano do Kimi (backend)

> Execute task a task, com TDD. No meio, rode só os testes da task
> (`php artisan test --compact <arquivo>`) e `./vendor/bin/pint`. O
> `composer ci:check` completo roda **uma vez**, no fim. Um commit por task.
> O front (Vue) é do Claude: não mexa em `resources/js`.

**Spec:** `docs/superpowers/specs/2026-10-15-banimento-design.md` (leia
inteira, é curta).

**Padrões do projeto pra copiar:** Action com `handle()` em
`app/Actions/Admin/` (ver `SetAdmin`, `SetPlayerPassword`); exceção de
regra em `app/Exceptions/AdminChangeNotAllowedException.php`; controller
fino com toast (`PlayerRoleController`); FormRequest com confirmação
digitada (`UpdateAdminRoleRequest`); comando (`PromoteAdminCommand`, usa
`User::findByEmailOrNicknameWithTag`); email (`PasswordChangedMail` +
`resources/views/mail/*`, layout `<x-mail-layout>`); avisos em tempo real
(`App\Actions\Notify\Notifier` + `BuildNotice::tradeFallen`).

## Review Focus

- Suspensão vencida não bloqueia mais nada (login, middleware, listagens), sem rodar nada agendado.
- Banir quem está suspenso: encerra a suspensão e aplica o ban (não recusa).
- Admin, a si mesmo, a conta do sistema e quem já está banido: recusados com mensagem em português.
- A conta do sistema nunca aparece em ranking, amigos, busca nem no admin.
- Espécime libertado continua com o mesmo `mint_number` e o histórico mostra "released".

---

### Task 1: Dados (enum, migrations, model, escopos)

**Files:**
- Create: `app/Enums/SanctionType.php`, `app/Models/Sanction.php`, `database/factories/SanctionFactory.php`
- Create: `database/migrations/2026_10_15_100000_create_sanctions_table.php`, `..._100001_add_is_system_to_users_table.php`, `..._100002_make_trade_id_nullable_on_specimen_transfers.php`, `..._100003_create_system_user.php`
- Modify: `app/Models/User.php`, `config/game.php`
- Test: `tests/Feature/Models/SanctionTest.php`

**Produces:** `SanctionType::{Suspension,Ban}`; `Sanction::scopeActive`,
`Sanction::message(): string`; `User::sanctions()`, `User::activeSanction(): ?Sanction`,
`User::scopeActive(Builder)` (não sistema e sem sanção ativa), `User::system(): User`,
cast `is_system`; `config('game.sanctions.suspension_days')` = `[1, 3, 7, 30]`.

```php
// app/Enums/SanctionType.php
enum SanctionType: string
{
    case Suspension = 'suspension';
    case Ban = 'ban';
}
```

```php
// create_sanctions_table (up)
Schema::create('sanctions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('type', 16);
    $table->text('reason');
    $table->timestamp('ends_at')->nullable();
    $table->timestamp('lifted_at')->nullable();
    $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['user_id', 'lifted_at']);
});
// down: Schema::dropIfExists('sanctions');
```

`add_is_system_to_users_table`: `$table->boolean('is_system')->default(false)->after('is_admin');` (down: dropColumn).
`make_trade_id_nullable`: `$table->foreignId('trade_id')->nullable()->change();` (down: volta a `->nullable(false)->change()`, apagando antes as linhas com `trade_id` nulo).

`create_system_user` (up): insere com `DB::table('users')->insert([...])`:
`email` `sistema@512app.local`, `password` `Hash::make(Str::random(64))`,
`nickname` `Professor Carvalho`, `tag` `0000`, `is_system` true,
`email_verified_at` e timestamps `now()`, demais campos nulos/padrão (confira as colunas
obrigatórias com `php artisan db:table users` ou a migration). down: apaga por `is_system`.

```php
// app/Models/Sanction.php (essencial; complete com #[Fillable], casts e relações user/issuer/lifter)
/** Ativa = não encerrada e (banimento ou prazo no futuro). */
public function scopeActive(Builder $query): void
{
    $query->whereNull('lifted_at')->where(fn (Builder $q) => $q
        ->where('type', SanctionType::Ban)
        ->orWhere('ends_at', '>', now()));
}

/** Texto que o jogador vê no login. */
public function message(): string
{
    return $this->type === SanctionType::Ban
        ? "Sua conta foi banida. Motivo: {$this->reason}"
        : 'Sua conta está suspensa até '.$this->ends_at?->timezone(config('app.timezone'))->format('d/m \à\s H:i').". Motivo: {$this->reason}";
}
```

```php
// app/Models/User.php
public function sanctions(): HasMany { return $this->hasMany(Sanction::class); }

public function activeSanction(): ?Sanction
{
    return $this->sanctions()->active()->latest('id')->first();
}

/** Jogadores visíveis pro grupo: sem a conta do sistema e sem sanção ativa. */
public function scopeActive(Builder $query): void
{
    $query->where('is_system', false)->whereDoesntHave('sanctions', fn (Builder $q) => $q->active());
}

public static function system(): self
{
    return self::query()->where('is_system', true)->firstOrFail();
}
```

Teste (`SanctionTest`): suspensão com `ends_at` futuro é ativa; vencida não; encerrada (`lifted_at`) não; ban sem `ends_at` é ativo; `User::active()` exclui sistema, suspenso e banido e inclui o de suspensão vencida; `message()` dos dois tipos. Commit: "Cria as sanções e a conta do sistema".

### Task 2: Ações (suspender, banir, encerrar) e email

**Files:**
- Create: `app/Actions/Admin/SuspendPlayer.php`, `BanPlayer.php`, `LiftSuspension.php`, `app/Actions/Admin/EndPlayerAccess.php`, `app/Exceptions/SanctionNotAllowedException.php`, `app/Mail/SanctionedMail.php`, `resources/views/mail/sanctioned.blade.php`
- Test: `tests/Feature/Actions/Admin/SanctionPlayerTest.php`

**Produces:**
- `SuspendPlayer::handle(?User $admin, User $target, int $days, string $reason): Sanction`
- `BanPlayer::handle(?User $admin, User $target, string $reason): Sanction`
- `LiftSuspension::handle(?User $admin, User $target): void`
- `SanctionNotAllowedException` com mensagens: `self()` "Você não pode se punir", `admin()` "Tire o papel de admin antes", `system()` "Essa conta é do sistema", `alreadySanctioned()` "Esse jogador já está suspenso ou banido", `invalidDays()` "Prazo inválido", `notSuspended()` "Esse jogador não está suspenso".

`EndPlayerAccess::handle(User $target): void` (usado pelos dois): apaga as sessões
(`DB::table('sessions')->where('user_id', ...)->delete()`), troca o `remember_token`
(`forceFill(['remember_token' => Str::random(60)])->save()`) e cancela as trocas abertas:

```php
$open = Trade::query()->open()->involving($target)->with('toUser.avatarSpecies')->get();
Trade::query()->whereKey($open->modelKeys())->update([
    'status' => TradeStatus::Cancelled,
    'cancel_reason' => TradeCancelReason::Unavailable,
    'responded_at' => now(),
]);
// avisa só quem propôs pra ele (a proposta dele some da caixa do outro sozinha)
foreach ($open->where('to_user_id', $target->id) as $trade) {
    $this->notifier->toPlayer($trade->from_user_id, $this->notices->tradeFallen($trade));
}
```

Regras comuns (checar antes, nessa ordem): `$admin?->is($target)` → self; `$target->is_system` → system; `$target->is_admin` → admin.

`SuspendPlayer`: `$days` fora de `config('game.sanctions.suspension_days')` → invalidDays; `activeSanction() !== null` → alreadySanctioned. Na transação: cria `Sanction` (`type` Suspension, `ends_at` `now()->addDays($days)`), `EndPlayerAccess`. Depois do commit: `Mail::to($target)->send(new SanctionedMail($target, $sanction))`.

`BanPlayer`: se a sanção ativa for ban → alreadySanctioned; se for suspensão, encerra ela (`lifted_at` now, `lifted_by` admin) dentro da transação. Cria a sanção Ban (`ends_at` nulo), `EndPlayerAccess`, apaga as amizades e pedidos (`Friendship::query()->involving($target)->delete()`) e liberta:

```php
$system = User::system();
$specimens = Specimen::query()->where('user_id', $target->id)->lockForUpdate()->get(['id']);
SpecimenTransfer::insert($specimens->map(fn (Specimen $s): array => [
    'specimen_id' => $s->id,
    'from_user_id' => $target->id,
    'to_user_id' => $system->id,
    'trade_id' => null,
    'transferred_at' => now(),
])->all());
Specimen::query()->whereKey($specimens->modelKeys())->update(['user_id' => $system->id]);
```

(Use `Specimen::query()` com o escopo global `RevealedScope` desligado, `withoutGlobalScopes()`, pra libertar também bola fechada.) Email depois do commit.

`LiftSuspension`: sanção ativa do tipo Suspension, senão notSuspended; grava `lifted_at`/`lifted_by`.

`SanctionedMail`: assunto "Sua conta foi suspensa" / "Sua conta foi banida"; corpo com o prazo (suspensão) e o motivo, no estilo do `PasswordChangedMail`.

Testes (`Mail::fake()`, `Event::fake([PlayerNotified::class])`): suspende e grava sanção + derruba sessão (insira uma linha em `sessions` e confira que sumiu) + troca o remember token + cancela troca aberta e avisa `trade_fallen` só pra quem propôs pra ele + email enviado; prazo inválido; admin/self/sistema recusados; já suspenso recusado; ban liberta (dono vira o sistema, `mint_number` igual, transferência com `trade_id` nulo, inclusive espécime não revelado), apaga amizades, encerra suspensão anterior; ban em banido recusado; encerrar suspensão. Commit: "Suspende, bane e liberta os espécimes do banido".

### Task 3: Bloqueio (login, middleware) e sumir das listagens

**Files:**
- Create: `app/Http/Middleware/EnsureNotSanctioned.php`
- Modify: `bootstrap/app.php` (append no `web`, depois do `HandleInertiaRequests`), `app/Http/Requests/LoginUserRequest.php`, `app/Http/Middleware/HandleInertiaRequests.php` (flash `sanction`), `app/Models/User.php` (`friendIds`), `app/Actions/BuildSpeciesRanking.php`, `app/Actions/Ranking/BuildShinyRanking.php`, `BuildIvRanking.php`, `BuildFirstMintRanking.php`, `app/Actions/Friends/BuildFriendList.php`, `app/Actions/Friends/SendFriendRequest.php` (`findTarget`), `app/Http/Controllers/Trainer/ShowTrainerController.php`, `ShowTrainerSpeciesController.php`, `app/Actions/Trainer/BuildComparison.php`, `app/Actions/Admin/SearchPlayers.php` (só tira o sistema)
- Test: `tests/Feature/Http/SanctionAccessTest.php`

- Middleware: usuário logado com `activeSanction()` → `Auth::guard('web')->logout()`, invalida a sessão, regenera o token e `redirect()->route('login')->with('sanction', $sanction->message())`. Conta do sistema logada (não deveria acontecer) → mesma coisa com "Essa conta é do sistema".
- `HandleInertiaRequests` `flash`: acrescenta `'sanction' => fn () => $request->session()->get('sanction')`.
- Login: depois do `Auth::attempt` bem-sucedido, se `Auth::user()->is_system` ou tiver sanção ativa → `Auth::guard('web')->logout()` e `ValidationException::withMessages(['email' => $sanction->message()])` (não conta como tentativa errada no rate limit).
- Listagens: `->active()` nas consultas de `User` dos rankings; `friendIds()` filtra os ids por `User::query()->active()->whereKey($ids)->pluck('id')`; `BuildFriendList` ignora amizades/pedidos com quem não está ativo; `findTarget` só acha ativo (mesma mensagem de "não achei"); Cartão de Treinador e ficha dele: `abort_unless(User::query()->active()->whereKey($user->id)->exists(), 404)`; `BuildComparison`: dono do "theirs" inativo → 404; `SearchPlayers`: `where('is_system', false)`.

Testes: suspenso é deslogado no próximo request e vê o flash; login recusado com a mensagem (suspenso, banido, sistema); suspensão vencida loga normal; ranking, amigos, busca, cartão (404) e comparar (404) sem o suspenso; o sistema fora do ranking e do admin. Commit: "Bloqueia e esconde quem está suspenso ou banido".

### Task 4: Admin (rotas, controllers, dados da lista, comandos, histórico)

**Files:**
- Create: `app/Http/Controllers/Admin/PlayerSanctionController.php` (`suspend`, `ban`, `lift`), `app/Http/Requests/Admin/SuspendPlayerRequest.php`, `BanPlayerRequest.php`, `app/Console/Commands/SuspendPlayerCommand.php`, `BanPlayerCommand.php`, `LiftSuspensionCommand.php`
- Modify: `routes/admin.php`, o `PlayerResource` (acrescenta `sanction` e `specimenCount`), `app/Actions/Admin/SearchPlayers.php` (eager load), `app/Actions/PresentSpecimenSheet.php`
- Test: `tests/Feature/Http/Admin/PlayerSanctionTest.php`, `tests/Feature/Console/SanctionCommandsTest.php`

- Rotas (no grupo admin): `POST jogadores/{user}/suspender` → `players.suspend`, `POST jogadores/{user}/banir` → `players.ban`, `POST jogadores/{user}/encerrar-suspensao` → `players.lift`.
- `SuspendPlayerRequest`: `days` required, `Rule::in(config('game.sanctions.suspension_days'))`; `reason` required, string, 5–500. `BanPlayerRequest`: `reason` igual + `confirm` igual ao `UpdateAdminRoleRequest` (`Nick#1234`).
- Controller: chama a Action; `SanctionNotAllowedException` → `back()->withErrors(['sanction' => $e->getMessage()])`; sucesso → `Inertia::flash('toast', "{$nick} suspenso por {$days} dias" | "{$nick} banido" | "Suspensão de {$nick} encerrada")` e `back()`.
- `PlayerResource`: `'sanction' => $sanction ? ['type' => ..., 'endsAt' => ?iso, 'reason' => ...] : null`, `'specimenCount' => int` (use `withCount('specimens')` e um eager load das sanções ativas no `SearchPlayers`, sem N+1).
- Comandos: `admin:suspend {player} {days} {reason}`, `admin:ban {player} {reason}` (pede `confirm()` a menos que `--force`), `admin:lift {player}`; `issued_by` nulo; mensagem de sucesso/erro como os outros comandos admin.
- `PresentSpecimenSheet`: transferência com `trade_id` nulo vira `'kind' => 'released'` (mesmos campos; `nickname` = quem foi libertado, `fromNickname` = o dono anterior).

Testes: não-admin 403; suspender/banir/encerrar pelo HTTP com toast; validação (prazo, motivo curto, confirmação errada); erro de regra volta com `errors.sanction`; lista do admin traz `sanction` e `specimenCount`; comandos; ficha com `released`. Commit: "Põe suspender, banir e encerrar no admin e nos comandos".

**Fim:** `composer ci:check` uma vez, verde. Relatório curto: o que fez, desvios e a saída do `ci:check`.
