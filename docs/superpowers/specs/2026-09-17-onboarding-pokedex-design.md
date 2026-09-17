# Onboarding pós-login, cadastro simplificado e `pokedex:sync`

Data: 2026-09-17
Status: aprovado, pronto pra virar plano de implementação

## Contexto

O cadastro atual pede tudo de uma vez (nickname, email, senha, data de
nascimento, código de convite). Decidimos quebrar isso em duas etapas, como
um jogo de celular: o cadastro em si fica só com o essencial pra criar a
conta (convite, email, senha), e o resto (nickname, data de nascimento,
avatar) é preenchido num wizard depois do primeiro login, sem recarregar
página.

O avatar do jogador passa a ser um ícone de Pokémon escolhido entre 15
espécies fixas, em vez do `avatar_seed` aleatório atual — o que exige que a
tabela `species` esteja populada e os sprites baixados localmente, que é o
`pokedex:sync` citado no `CLAUDE.md` e na Fase 1 do `SPEC.md`, mas que nunca
foi implementado.

De quebra, isso força a existência de guards de rota de verdade (hoje só
`login`/`cadastro` usam `guest`; `/` é pública, não existe `logout`).

## Fora de escopo desta spec

- **Painel de administração** (gestão de convites): fica pro próximo
  subprojeto, com brainstorm próprio. O middleware `EnsureUserIsAdmin` só
  será criado junto dele — não faz sentido código de guard sem nenhuma rota
  usando.
- **Tela de Início** (subprojeto B) e **abertura de pack** (subprojeto C):
  o `pokedex:sync` desta spec é pré-requisito de ambos, mas as telas em si
  não entram aqui.

## A. Comando `pokedex:sync`

`App\Console\Commands\PokedexSync`, sem parâmetros (sempre sincroniza as 151).

**Fonte de dados**: PokeAPI REST, um id de 1 a 151:
- `GET /pokemon/{id}` — nome, tipos, sprites, `height`/`weight` (decímetro e
  hectograma — converter pra metro e kg dividindo por 10).
- `GET /pokemon-species/{id}` — `base_stat_total` vem de somar `stats[].base_stat`
  do endpoint `/pokemon/{id}`, não do species. `flavor_text_entries` filtrado
  por idioma pt (`language.name === 'pt-BR'` ou `'pt'` se existir); se a API
  não tiver entrada em português pra aquele pokémon, `flavor_pt` fica `null`
  (a coluna já é nullable — não é erro, é dado ausente).
- `GET /evolution-chain/{id}` (via link do species) — usado só pra achar a
  posição do pokémon na cadeia (1º, 2º ou 3º estágio) e preencher
  `evolution_stage`. Pokémon sem evolução conhecida (ex. Tauros) = estágio 1.

**Sprites**: baixados do repositório `PokeAPI/sprites` (conforme item 3 da
seção 7 do `SPEC.md` — nunca hotlink em runtime), três por espécie:
sprite normal, sprite shiny, artwork oficial. Salvos no disco `public` em
`species/{id}/sprite.png`, `species/{id}/shiny.png`, `species/{id}/artwork.png`.
`sprite_path`/`sprite_shiny_path`/`artwork_path` gravam esse caminho relativo.

**Persistência**: `Species::updateOrCreate(['id' => $dexNumber], [...])` —
comando idempotente, rodar de novo atualiza em vez de duplicar ou falhar por
unique constraint. `rarity_tier` é calculado chamando
`app(RarityTable::class)->classify($species)` (serviço já existe, só
reaproveita) depois dos outros campos estarem setados, já que a classificação
depende de `evolution_stage` e `base_stat_total`.

**Saída no console**: barra de progresso (`$this->output->progressStart(151)`),
e um resumo final de quantas espécies tiveram `flavor_pt` ausente (aviso, não
erro).

## B. Mudanças de schema

Edita as migrations existentes (ainda não existe usuário real em nenhum
ambiente, então segue a convenção já usada no projeto de editar em vez de
empilhar):

- `2026_09_15_145533_add_cadastro_fields_to_users_table.php`: `nickname`,
  `tag` e `birthdate` perdem o `NOT NULL` (viram `->nullable()`). Remove a
  coluna `avatar_seed` (string) e cria `avatar_species_id`
  (`unsignedSmallInteger`, nullable, `->constrained('species')` — tem que
  ser `unsignedSmallInteger` pra bater com o tipo de `species.id`, que é
  `unsignedSmallInteger` primary key, não bigint; foi exatamente esse
  descasamento que já causou um bug real antes, com `specimens.species_id`).
- `down()` de ambas as migrations atualizado pra desfazer exatamente essas
  mudanças.
- `App\Models\User`: adiciona `avatarSpecies(): BelongsTo<Species, $this>`,
  atualiza `#[Fillable]` (troca `avatar_seed` por `avatar_species_id`),
  adiciona o método `hasCompletedProfile(): bool` (`return $this->nickname !== null;`).
  Sem coluna nova de "onboarding completo" — o estado é derivado.

## C. Cadastro simplificado

- `RegisterUserRequest`: regras ficam só `email`, `password` (com
  `confirmed`, que já existe), `invite_code`. Remove `nickname` e
  `birthdate`. `RegisterUserData` perde os campos correspondentes.
- `RegisterUser` (Action): cria o `User` só com `email`, `password`,
  `invited_by`, `client_seed` — `nickname`/`tag`/`birthdate`/`avatar_species_id`
  ficam `null`. Resto da lógica (transação, lock no convite, `InviteRedemption`,
  `increment('uses_count')`, envio de verificação de email) não muda.
- `resources/js/Pages/auth/Register.vue`: form perde os campos de nickname e
  data de nascimento. UX/UI reaproveita os componentes do design system já
  usados (glass panel, `AppInput`, `AppButton`) — ajustar copy e espaçamento
  agora que o form ficou mais curto.

## D. Onboarding (pós-login, antes de jogar)

**Rotas** (`routes/auth.php`, grupo `auth` — sem exigir perfil completo, senão
vira loop de redirect):
- `GET completar-perfil` → `Onboarding\ShowOnboardingController`, nome
  `onboarding.show`. Se `auth()->user()->hasCompletedProfile()`, redireciona
  pra `home` em vez de renderizar (evita rever o wizard depois de pronto).
- `POST completar-perfil` → `Onboarding\CompleteOnboardingController`, nome
  `onboarding.store`.

**Frontend**: uma página Inertia só, `resources/js/Pages/Onboarding/Show.vue`,
com um `ref` local de passo (1/2/3). Troca de passo é 100% client-side — zero
requisição até o fim, sem estado de loading entre telas. Visual: mesma
linguagem "Nintendo Switch 2" do design system (glass panel, tema
claro/escuro já funcional).

- **Passo 1 — nickname**: input de texto, mesma validação de tamanho do
  cadastro antigo (`min:2`, `max:20`).
- **Passo 2 — data de nascimento**: date picker, mesma regra (`before:today`).
- **Passo 3 — avatar**: grid com as 15 espécies abaixo, cada ícone é o PNG de
  `artwork_path` centralizado dentro de um círculo branco (`rounded-full
  bg-white`, sombra leve) — sem asset novo, só CSS. Seleção fica destacada
  (borda/glow na cor de marca).

Lista fixa das 15 espécies (`config/game.php`, chave nova `onboarding.avatar_species_ids`,
editável sem tocar em código):

```
Pikachu (25), Bulbasaur (1), Charmander (4), Squirtle (7), Charizard (6),
Eevee (133), Snorlax (143), Gengar (94), Mewtwo (150), Mew (151),
Jigglypuff (39), Psyduck (54), Gyarados (130), Dragonite (149), Meowth (52)
```

Ao confirmar o passo 3, um único POST manda `nickname`, `birthdate` e
`avatar_species_id` juntos. **Trade-off aceito**: se a pessoa fechar o app no
meio do wizard, os dados não ficam salvos e ela recomeça do zero no próximo
login — aceitável pra um wizard de 3 telas rápidas entre amigos.

**Backend**: nova Action `App\Actions\CompleteOnboarding`, assinatura
`handle(User $user, string $nickname, Carbon $birthdate, int $avatarSpeciesId): User`.
Gera a `tag` reaproveitando `GenerateUserTag::handle($nickname)`, valida que
`avatarSpeciesId` existe em `config('game.onboarding.avatar_species_ids')`
(não confia em qualquer id vindo do client), salva os 4 campos de uma vez.
`CompleteOnboardingController` valida via `CompleteOnboardingRequest`
(FormRequest próprio) e chama a Action — controller fino, igual o resto do
projeto.

## E. Guards de rota

Novo middleware `App\Http\Middleware\EnsureProfileIsComplete`, alias
`onboarded`: se `auth()->user()->hasCompletedProfile()` é `false`, redireciona
pra `route('onboarding.show')`; senão, `$next($request)`.

- `routes/web.php`: `Route::inertia('/', 'Welcome')` entra em
  `Route::middleware(['auth', 'onboarded'])->group(...)`. Deslogado que
  acessar qualquer rota desse grupo cai automaticamente no `/login`
  (comportamento padrão do Laravel, `route('login')` já existe).
- `routes/auth.php`: rotas de onboarding ficam só em `auth` (não em
  `onboarded` — são exatamente a exceção).
- **Logout**: novo `POST /logout` → `Auth\LogoutUserController` (invalida
  sessão, regenera token CSRF, redireciona pro `login`), dentro de `auth`
  puro — tem que dar pra sair mesmo com perfil incompleto.
- Guard de admin: **não entra aqui**, ver "Fora de escopo".

## F. Testes (Pest, `RefreshDatabase`, seguindo o estilo dos testes existentes)

- `PokedexSyncTest` (Feature ou Unit com HTTP fake): roda o comando com
  `Http::fake()` simulando as respostas da PokeAPI, assert que popula 151
  linhas em `species` com `rarity_tier` correto pra uma amostra conhecida
  (ex. Mewtwo = lendário), assert que rodar 2x não duplica (idempotência).
- `RegisterUserTest`/`RegisterUserControllerTest`: ajustados pra refletir os
  campos removidos (não passam mais `nickname`/`birthdate`), assert que o
  usuário criado tem esses campos `null`.
- `CompleteOnboardingTest` (Action): determinismo — mesma entrada, mesma tag
  gerada respeitando colisão; assert que rejeita `avatar_species_id` fora da
  lista permitida.
- `CompleteOnboardingControllerTest`: fluxo HTTP completo, assert redirect
  pra `home` no fim.
- Guards (`Feature/Http` ou um teste dedicado de middleware): deslogado
  acessando `/` → redirect `login`; logado sem perfil → redirect
  `onboarding.show`; logado com perfil completo tentando abrir
  `completar-perfil` → redirect `home`; logout desloga de verdade.

## G. Usuário admin

Criado via `tinker`, não seeder versionado (dado de ambiente, não código):
email + senha fornecidos, `is_admin = true`, `email_verified_at` já
preenchido, `invited_by = null` (bypassa o convite). Nickname/tag/data/avatar
ficam `null` — completa pelo wizard normal no primeiro login, testando o
fluxo real em vez de ganhar tratamento especial.
