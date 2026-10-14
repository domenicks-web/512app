# CLAUDE.md

Leia o `SPEC.md` antes de qualquer tarefa. Ele tem o domínio, o schema, as taxas e
as regras do jogo. Este arquivo cobre só como escrever o código.

## Contexto

Jogo de abrir pack de Pokémon geração 1. PWA privado, entre amigos, sem monetização.
Laravel 12 + Inertia + Vue 3 + Tailwind + PostgreSQL.

Responder e comentar em português.

## Estrutura

```
app/
  Actions/                 uma ação de negócio por classe, método handle()
    OpenPack.php
    ResolveSpecimen.php
    ProposeTrade.php
    AcceptTrade.php
  Services/
    ProvablyFair.php       commit, reveal, verify, derive
    RarityTable.php        pesos e classificação das 151
  Models/
  Http/Controllers/        finos, só validam e delegam pra Action
  Console/Commands/
    PokedexSync.php
resources/js/
  Pages/                   páginas Inertia
  Components/
  composables/
config/
  game.php                 TODAS as taxas e constantes do jogo
tests/
```

## Regras de código

1. **Controller não tem lógica.** Valida com FormRequest, chama a Action, devolve
   Inertia. Se um controller passar de 20 linhas por método, a lógica tá no lugar
   errado.

2. **`ResolveSpecimen` é função pura.** Assinatura:
   `handle(string $seed, Species $species, int $mintNumber): SpecimenData`.
   Nunca chama `rand()`, `random_bytes()`, `now()`, nem toca no banco. Toda
   aleatoriedade entra pela seed. Isso é obrigatório pro provably fair funcionar.

3. **Nenhum sorteio no cliente.** O front nunca decide nada. Recebe o resultado
   pronto e só anima. Não gerar rolagem em JS nem "por enquanto".

4. **Nada de número mágico.** Taxa de shiny, pesos de raridade, cooldown do pack,
   faixas de tamanho: tudo em `config/game.php`.

5. **Transação com lock no `mint_number`.** Duas aberturas simultâneas da mesma
   espécie não podem pegar o mesmo número. Usar `lockForUpdate` ou sequence.

6. **Nunca hotlinkar sprite.** Tudo vem do storage local, populado pelo
   `pokedex:sync`. Se precisar de imagem nova, adiciona no command.

7. **Vocabulário.** `Species` é a espécie, `Specimen` é a instância única do
   jogador. Não usar "card", "pokemon" solto, nem "instance" no lugar de Specimen.

8. **Pest pra testes.** Obrigatório testar: determinismo do `ResolveSpecimen`
   (mesma seed, mesmo resultado, sempre), cooldown do pack, e distribuição das
   raridades em amostra grande.

## Convenções

- PHP: tipagem estrita, `declare(strict_types=1)`, Pint com preset Laravel
- Migrations sempre com `down()`
- Enums nativos do PHP pra tipo, natureza, tier e status
- Vue: Composition API com `<script setup>`, sem Options API
- Tailwind: classes utilitárias, sem `@apply` a não ser em caso repetido de verdade
- Commits em português, imperativo, sem prefixo de conventional commits

## Comandos

```bash
php artisan pokedex:sync       # importa as 151 e baixa os sprites
php artisan test               # Pest
./vendor/bin/pint              # formata
npm run dev                    # Vite
```

## Antes de propor algo

Se a tarefa envolver batalha, evolução, moeda, geração 2 em diante ou dinheiro
real, pare e avise: está fora de escopo pelo `SPEC.md`. Não implemente.

Se a tarefa parecer exigir que o cliente calcule um resultado, pare e avise.
Sempre tem outro jeito.

## Checkpoint (17/09, sessão em pausa)

Onde paramos, pra retomar sem perder contexto. Tudo abaixo já está commitado e
pushado pro `main`.

- **Onboarding pós-login, de verdade.** Cadastro (`/cadastro`) ficou só
  convite + email + senha — `nickname`, `tag`, `birthdate` e
  `avatar_species_id` viraram nullable em `users` e são preenchidos depois,
  no primeiro login, por um wizard de 3 passos (`onboarding/Show.vue`:
  nickname → data de nascimento → avatar) atrás de `GET`/`POST
  completar-perfil`. `User::hasCompletedProfile()` (`nickname !== null`)
  decide quem já passou por ali. Action `CompleteOnboarding` reaproveita o
  `GenerateUserTag` que o cadastro usava antes.

- **`avatar_seed` (string aleatória) morreu, virou `avatar_species_id`**
  (FK pra `species.id`, ambos `unsignedSmallInteger` — mesmo cuidado de tipo
  que já tinha corrigido `specimens.species_id` antes). O avatar é escolhido
  entre 15 espécies fixas em `config('game.onboarding.avatar_species_ids')`,
  devolvidas na ordem curada do config (não na ordem crua do banco).

- **Comando `pokedex:sync` construído e já rodado contra a API real**: puxa
  as 151 espécies da PokeAPI, baixa sprite/shiny/artwork pro storage local
  (nunca hotlink), classifica raridade via `RarityTable` já existente. Achei
  e corrigi um bug de dado real: a PokeAPI devolve o tipo *atual* de algumas
  espécies (ex. Jigglypuff é `fairy` desde a geração 6), mas o enum
  `PokemonType` só tem os 15 tipos originais da gen 1 — o comando agora usa
  `past_types` da API pra recuperar o tipo de gen 1 quando existe divergência.

- **Guards de rota**: middleware `EnsureProfileIsComplete` (alias
  `onboarded`) — deslogado cai no `/login` (`auth` padrão do Laravel), logado
  sem perfil cai no onboarding, logado com perfil vê a home. Rota de logout
  (`POST /logout`) existe mas só tem um link provisório na `Welcome.vue`
  (que ainda é o placeholder padrão do Laravel — a Tela de Início de
  verdade é o subprojeto B).

- **Bug real achado no meio do caminho**: `<Form v-bind="store()">` no
  Login/Cadastro só "funcionava" por coincidência (a URL da página é igual
  à URL do POST nos dois casos). O componente `Form` do Inertia espera o
  par `{url, method}` inteiro na prop `action` (`action: string |
  {url, method}`), não espalhado via `v-bind`. Corrigido nos três lugares
  pra `:action="store()"` — vale lembrar disso em qualquer form novo.

- **Visual do onboarding, tratamento "Nintendo Switch 2"**: retrato borrado
  de fundo com os sprites reais (reflete o avatar escolhido), anel
  holográfico (vermelho→dourado→violeta, tokens `--color-gold`/
  `--color-violet`/`--holo-gradient` em `app.css`) no avatar selecionado e
  na trilha de passos — mesma linguagem que o `SPEC.md` reserva pro shiny
  na abertura de pack. Cartão de treinador que preenche em tempo real
  (nickname + tag-prévia + avatar). Tema claro vira acrílico vermelho-claro
  (não cinza neutro) em vez de simplesmente inverter as cores. Cortina de
  entrada/saída ("Entrando no mundo...") e slide/fade real entre os 3
  passos — tudo em CSS puro (`transform`/`opacity`), sem lib de animação,
  respeitando `prefers-reduced-motion`.

- **Usuário admin criado**: `ramondfernandes@gmail.com`, `is_admin = true`
  (setado via `forceFill` — não é mass-assignable de propósito). Perfil foi
  resetado pra `null` mais de uma vez durante os testes desta sessão; se
  estiver com perfil completo ao retomar e precisar ver o onboarding de
  novo, resetar via tinker (`forceFill(['nickname'=>null, 'tag'=>null,
  'birthdate'=>null, 'avatar_species_id'=>null])->save()`).

- **Admin pronto (13/10)**: `/admin/convites` (criar com `max_uses` e
  validade opcionais, listar status/quem usou, revogar via `revoked_at`) e
  `/admin/usuarios` (promover/rebaixar admin, travas de auto-rebaixamento e
  último admin). Middleware `admin`, Actions em `app/Actions/Admin/`,
  comando `php artisan invites:create [--by=email] [--max-uses=N]
  [--unlimited] [--expires-in-days=N]`. Visual provisório (`AdminShell`),
  sem tratamento de design ainda.

- **Conta de teste local (13/10)**: `claudetest@512app.com` / `claude`,
  `Claude#9805`, perfil completo, sem admin, sem espécimes, sem amigos. Criada a pedido
  do usuário pro Claude testar no navegador. O MCP do Playwright não acha o
  Chrome; funciona via script Node com o `playwright` do cache do npx
  apontando `executablePath` pro `chromium_headless_shell-1243` em
  `~/.cache/ms-playwright`.

- **Ambiente local**: MySQL sobe com `docker compose up -d`. Dev server
  (`composer run dev`) pode ter ficado rodando em background desta sessão —
  se a porta 8000/5173 já estiver ocupada ao retomar, é provavelmente ele.
  `storage:link` já foi rodado no ambiente local.

- **Fluxo de entrada no design do handoff (13/10)**
  (`design_handoff_512_onboarding/`): loading → login → cadastro →
  onboarding → home, tudo sob o layout persistente `EntryLayout` (campo de
  512 pontos em canvas, `useDotField`, sobrevive às visitas). Animação em
  WAAPI puro com molas `linear()` (`resources/js/lib/motion.ts`), sem lib.
  Só tema claro nessas telas (`.entry-light` devolve os tokens claros).
    - `/cadastro` é em 2 passos com pills: convite (validado no servidor e
      guardado na sessão) → email/senha. Login, cadastro, convite e
      `completar-perfil` respondem JSON (204/422) via `useHttp`: o cliente
      roda a animação da pokébola enquanto espera e só navega depois.
    - Tag do onboarding: prévia sorteada no servidor
      (`GET completar-perfil/tag`, guardada na sessão) e gravada no fim se
      o par nickname+tag ainda estiver livre. O cliente nunca sorteia.
    - Abertura (loading) mora no `EntryLayout` e toca inteira a cada
      carregamento do app (F5, primeiro acesso), em qualquer tela do fluxo,
      mesmo que a página carregue antes. Visita do Inertia não toca de novo.
      As páginas esperam `waitForIntro()` antes de entrar; no login o logo
      voa até o card, nas outras ele amassa e some.
    - Home nova (`pages/Home.vue`, `ShowHomeController` +
      `BuildHomeSummary`); a `Welcome.vue` provisória morreu. Logout de
      verdade e link do admin ficam no menu "Ajustes" do dock.

- **Subprojeto B pronto (13/10; decisões revisadas com o usuário)**:
    - Home sem nenhum dado fictício; `game.home.placeholders` morreu.
      `BuildHomeSummary` agora junta: posição no ranking
      (`BuildSpeciesRanking`), trocas pendentes *recebidas*, destaque do
      feed (`FindFeedHighlight`) e amigos online
      (`GroupPresence` + `User::friendIds()`).
    - Telas novas atrás de `auth` + `onboarded`, todas com a moldura
      `components/group/GroupScreen.vue` (voltar, título, dock com o item
      ativo): `/colecao` (151 em ordem de dex, silhueta pra quem falta,
      filtro Todas/Tenho/Faltam), `/ranking`, `/trocas` (só leitura) e
      `/amigos`. Tiles Coleção, Ranking e Trocas da Home abrem as telas.
    - Decisões:
        - **Amizade de verdade** (pedido por `nickname#tag`, amizade
          automática com quem convidou, contador no dock) — decidido com o
          usuário depois, ver `docs/superpowers/specs/2026-10-13-amizade-design.md`.
          Ranking e feed continuam com o grupo inteiro.
        - **Online = sessão com atividade nos últimos
          `game.group.online_window_minutes` (2)**, lido da tabela
          `sessions`, com `usePoll` a cada `game.group.presence_poll_seconds`
          (60) na Home e em Amigos — a recarga também é o "ping". Só
          funciona com `SESSION_DRIVER=database`. Sem Reverb (fase 4).
        - **Ranking só por espécies distintas.** Empate divide a posição,
          shinies só desempatam a ordem. Quem tem 0 espécies fica sem
          posição ("—" na Home). Os outros rankings da fase 3 (shinies,
          IV, mint) ficaram de fora.
        - **Feed = abertura mais recente que é shiny ou de tier em
          `game.group.feed_highlight_tiers`** (raro, lendário). "Quem
          abriu" é o dono da `opening`, não o dono atual.
        - **Trocas é só leitura**: propor/aceitar/recusar é a fase 4
          (confirmado pelo usuário).
        - **Coleção com silhueta e "???"** pra espécie que falta
          (aprovado como está).
    - Conferido no navegador (desktop e 390px) com a conta de teste, só nos
      estados vazios — ainda não existe espécime no banco local.

- **Pendências** (por fase do `SPEC.md`):
    - **Ajustes no que já existe:**
        - Home: o tile "Pack de hoje" e o item Packs do dock não fazem
          nada — abrir pack de verdade é o subprojeto C. O "1 em 512" e a
          data são decorativos.
        - PWA ainda não existe (sem `vite-plugin-pwa`, sem manifest),
          apesar de estar no SPEC. É o que faz o jogo parecer app no
          celular (instalar na tela inicial).
        - Mobile: a grade de parceiros do onboarding encolhe com `zoom`
          abaixo de 460px; revisar num aparelho de verdade.
        - A confirmação "Desfazer amizade com X?" fica apertada no cartão
          (quebra em 3 linhas).
        - Tile do Feed na Home não abre tela (feed completo é fase 5).
        - Configurar o Gmail SMTP no `.env` (conta e senha de app do
          usuário). Sem isso os emails do perfil vão só pro log.
        - "Esqueci a senha" (recuperação por email) ainda não existe.
        - O email de verificação do cadastro ainda é o padrão do Laravel
          (sem o visual do jogo).
        - Mensagens da regra de senha forte (produção) saem em inglês — o
          projeto não tem `lang/pt_BR`.
    - **Fase 2 — a prova (provably fair):** commit e reveal de seeds
      (hash mostrado antes, seed revelada depois), página `/verificar`,
      ficha detalhada do espécime (IVs, natureza, tamanho, hash). Decidir
      no brainstorm do C se commit/reveal já entra junto.
    - **Fase 3 — o social:** perfil público de cada jogador; comparar dois
      espécimes da mesma espécie lado a lado; rankings que faltam (mais
      shinies, maior IV, menor mint — hoje só existe o de espécies).
    - **Fase 4 — as trocas:** propor, aceitar e recusar (hoje `/trocas` é
      só leitura); notificação em tempo real via Reverb (também
      substituiria o poll de 60s de presença e pedidos de amizade);
      histórico de donos anteriores de cada espécime.
    - **Fase 5 — o polimento:** tilt com giroscópio, som de abertura,
      apelido no espécime e feed completo do grupo.
- **Editar perfil (13/10)**: `/perfil` (menu Ajustes → "Editar perfil"),
  design em `docs/superpowers/specs/2026-10-13-editar-perfil-design.md`.
  Avatar livre; nickname a cada `game.profile.nickname_cooldown_days` (15),
  mantendo a tag se der; data de nascimento corrigível uma vez (admin
  libera outra em `/admin/usuarios`); email por link assinado pro endereço
  novo (60 min) + aviso pro antigo; senha com senha atual, confirmação,
  popup próprio (`ConfirmDialog`), derruba as outras sessões e avisa por
  email. Emails estilizados em `resources/views/mail/` com o layout
  `<x-mail-layout>`. `.env.example` já traz o bloco do Gmail SMTP — falta o
  usuário criar a conta e a senha de app e preencher o `.env`.
    - `ConfirmDialog` só monta o `Teleport` depois do `onMounted`: o SSR do
      Inertia não injeta teleports no `<body>` e a hidratação quebrava.
- **Sprites não seguem o mouse (13/10, pedido do usuário)**: tirado da
  `PartnerGrid` (onboarding e perfil) o "olhar" pro cursor/escolhido
  (`scaleX(-1)` + inclinação) e, na Home, o avatar virando pro tile ativo —
  mesmo o handoff pedindo. Não recolocar.
- **Sprites com tamanho uniforme (13/10)**: todo sprite de espécie passa
  por `components/FittedSprite.vue`, que mede no navegador a área desenhada
  do PNG (`lib/spriteBounds.ts`, cache por URL) e encaixa com a mesma margem.
  Sprite novo na UI: usar o `FittedSprite`, não `<img>` solto. O voo do
  parceiro até o avatar (handoff) mora em `composables/useSpriteFlight.ts`
  e é usado no onboarding e no editar perfil.
- **CI (13/10)**: voltou a rodar no `main` (PHP 8.4, SQLite no setup,
  PHPStan com 1G). `composer ci:check` local reproduz o CI inteiro.
  Formatador ignora handoff, `docs/superpowers/` e `CLAUDE.md`.

- **Estado do git ao pausar (13/10, 22h50)**: o branch `perfil` (editar
  perfil, emails estilizados, sprites uniformes, voo do parceiro, sprites
  sem seguir o mouse) **não foi mergeado nem enviado** — o usuário ainda não
  decidiu. `main` está em dia com o `origin`. Gmail SMTP já configurado no
  `.env` local (`512jogo@gmail.com`) e testado com envio real.

- **Próxima sessão — combinado com o usuário** (cada item com brainstorm
  antes de codar):
    1. **Avatar da Pokédex no perfil**: além das 15 opções fixas, escolher
       qualquer espécie que o jogador tem na coleção, com sprite shiny se
       tiver shiny dela. **Decidido (14/10):** perdeu/trocou o espécime,
       o avatar volta pro Pikachu (primeiro do config). Em aberto: shiny e
       normal da mesma espécie como opções separadas (sugestão do Claude)
       ou uma só mostrando o shiny se tiver; e a seção vazia até existir
       abertura de pack.
    2. ~~Admin de verdade~~ — feito em 14/10 (ver abaixo).
    3. **Subprojeto C — abertura de pack** (detalhe abaixo).

- **Admin de verdade (14/10, branch `admin` a partir do `perfil`)**: design
  em `docs/superpowers/specs/2026-10-14-admin-design.md`, handoff em
  `design_handoff_512_onboarding/ADMIN.md`. `/admin/convites` (gerador
  caça-níquel com sufixo sorteado no servidor via `GET convites/sufixo` e
  guardado na sessão; usos 1–10; validade 7/30/sem) e `/admin/jogadores`
  (busca, cota de convites 0–9, papel com confirmação digitada `Nick#1234`,
  menu "⋯" com liberar data, reenviar confirmação e trocar senha de outro
  jogador). Gate `admin` no lugar do middleware. Status do convite agora
  na ordem revogado → esgotado → expirado; convite infinito deixou de
  existir. Comandos `admin:promote|demote`, `invite:create|revoke|list`
  (o `invites:create` morreu). `invite_quota` só armazenada.
- **Dev na rede local (14/10)**: `SERVER_HOST=0.0.0.0` + `DEV_LAN_HOST` no
  `.env` (IP do PC, hoje 192.168.1.26) e `APP_URL` com esse IP → celular no
  mesmo Wi-Fi abre `http://192.168.1.26:8000`. Copiar texto no app usa
  fallback (`execCommand`) porque http por IP não é contexto seguro.

- **Próximo subprojeto** (com brainstorm antes de codar):
    - **C — Tela de abertura de pack** (fundo escuro): a maior. Envolve criar a
      action `OpenPack` (transação + lock em `mint_number`, chamando
      `ResolveSpecimen`), rota, e a animação de revelação em GSAP.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.

- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
