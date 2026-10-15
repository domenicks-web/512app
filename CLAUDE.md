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

- **Pendências** (revisadas com o usuário em 14/10):
    - **Ajustes pra depois** (combinado: fazer depois do que estiver em
      andamento):
        - Tile "Pack de hoje" da Home: mostrar "Disponível" ou a contagem
          até o próximo pack (hoje a data e o "1 em 512" são decorativos).
        - Tile do Feed na Home não abre tela.
        - "Esqueci a senha" (recuperação por email) não existe.
        - Mensagens da regra de senha forte (produção) em inglês — falta
          `lang/pt_BR`.
        - Grade de parceiros do onboarding encolhe com `zoom` abaixo de
          460px; conferir no S26+ do usuário.
        - Confirmação "Desfazer amizade com X?" quebra em 3 linhas.
        - PWA (manifest, instalar na tela inicial), previsto no SPEC.
    - **Fase 2 — a prova:** commit/reveal, ficha e comprovante já
      existem (abertura do pack). Falta a página `/verificar` (colar
      seeds e nonce e refazer a conta).
    - **Fase 3 — o social:** perfil público; comparar dois espécimes da
      mesma espécie; rankings de mais shinies, maior IV e menor mint.
    - **Fase 4 — as trocas:** propor/aceitar/recusar; Reverb (substitui o
      poll de 60s); histórico de donos. **Banimento entra aqui**
      (decidido 14/10: temporário ou permanente, email com o motivo).
    - **Fase 5 — o polimento:** tilt com giroscópio, apelido no espécime,
      feed completo do grupo.
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
       o avatar volta pro Pikachu (primeiro do config); shiny e normal da
       mesma espécie são opções separadas (cada uma exige ter aquele
       espécime). Próximo a implementar.
    2. ~~Admin de verdade~~ — feito em 14/10 (ver abaixo).
    3. ~~Subprojeto C — abertura de pack~~ — feito em 14/10 (ver abaixo).

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

- **Abertura do pack (14/10, branch `pack`, a partir do `admin`)**: design
  em `docs/superpowers/specs/2026-10-14-abertura-do-pack-design.md`,
  handoff em `design_handoff_512_onboarding/ABERTURA.md`. `/pack`:
  commit da server seed antes (`users.next_server_seed`, criptografada),
  `DrawPack` (puro, HMAC por slot) + `OpenPack` (cooldown 24h, lock no
  usuário e nas espécies pro mint) + `RevealSlot` (cada bola só revela o
  conteúdo quando clicada; seed revelada na 5ª). Espécime de bola fechada
  some do resto do jogo pelo `RevealedScope` (global no `Specimen`).
  Front em WAAPI (não GSAP, pra manter as curvas do protótipo 1:1):
  `lib/pack/director.ts`, `fx.ts`, `sfx.ts`, `v-pixel-fit`. Mobile no
  formato "5 do dado" (decidido com o usuário, substitui o 4+1 do
  handoff). `pokedex:sync` agora baixa os GIFs animados (já rodado).
- **Depois da abertura (14/10, madrugada)**:
    - **Bug do celular resolvido de vez**: o SSR do Inertia punha o CSS
      dos componentes com `localhost:5173` no HTML; no celular isso não
      carrega. Plugin `lanSsrOrigin` no `vite.config.ts` troca pela URL da
      rede quando `DEV_LAN_HOST` existe. Layout crítico do pack e das
      tabelas do admin foi pro Tailwind (sem depender de `<style>`).
    - Coleção: carta clicável abre a ficha (clara, com "1 de N" quando há
      vários espécimes; `GET colecao/{species}` em JSON) e cartas com
      moldura/brilho por raridade (`lib/pack/rarity.ts`, `CollectionCard`).
    - Admin: "Liberar próximo pack" no menu ⋯ (zera `next_pack_at`).
    - Cerimônia: meta em blocos (Tamanho/IV/Natureza) e selo "Primeiro X
      do servidor"; resumo em 2 colunas no celular.
    - **Dados do jogo zerados** a pedido do usuário (specimens, openings,
      trades, nonce, cooldown e seeds comprometidas). Contas e convites
      mantidos.
- **Estado do git (14/10)**: `perfil`, `admin` e `pack` mergeados no
  `main` (fast-forward) e enviados. CI verde em `d62efd1` (o Wayfinder
  tipa `{species}` como string no MySQL e number no SQLite do CI — a
  coleção usa `speciesKey()` pra não depender disso).
- **Ajustes de 14/10 à tarde (branch `ajustes`, a partir do `main`,
  não mergeado nem enviado)**:
    - Pokébola vermelha do dock volta pra Home.
    - Tile do pack mostra "Disponível" ou "Próximo em hh:mm:ss"
      (`nextPackAt` no `BuildHomeSummary`); a data e o "1 em 512" saíram.
    - Tela `/feed` (`BuildGroupFeed`, limite `game.group.feed_limit`);
      `FindFeedHighlight` virou o primeiro item dela. Tile do Feed abre ela.
    - `lang/pt_BR` (validation, auth, passwords) e `APP_LOCALE=pt_BR`.
    - **Esqueci a senha**: `/esqueci-a-senha` (204 sempre, não entrega se o
      email existe) → email `ResetPasswordMail` → `/nova-senha/{token}`
      guarda token+email na sessão e redireciona pra `/nova-senha` limpa
      (só senha + confirmação) → derruba **todas** as sessões, troca o
      remember token, avisa por email (`byReset`) e **não loga**: popup
      "Senha trocada" leva pro login com `?email=` preenchido (decidido
      com o usuário). Login ganhou "Manter conectado" e link "Esqueci a
      senha". Entrada dos cards em `lib/cardEntrance.ts`.
    - **Avatar da Pokédex**: `users.avatar_is_shiny`;
      `ListAvatarOptions` (fixos + coleção; shiny só com espécime shiny)
      valida no `UpdateAvatarRequest`. Sprite do avatar em todo lugar via
      `User::avatarSpritePath()`. `ResetUnownedAvatar` volta pro Pikachu,
      **mas ainda não é chamada em lugar nenhum**: o `AcceptTrade` da
      fase 4 precisa chamar pros dois lados.
    - Confirmação de desfazer amizade em 2 linhas fixas (nick cortado).
    - Grade de parceiros abaixo de 460px conferida a 360/390/412: cabe.
- **Dock e abas (14/10, tarde)**: Home e telas do grupo moram no layout
  persistente `GroupLayout` (dentro do `EntryLayout`): o dock não é
  recriado e o conteúdo desliza na ordem do dock (Home → Coleção
  → Ranking → Trocas → Amigos → Feed → Perfil). Cascata de entrada só na
  primeira tela (`groupNavigationKey`). Dock: casa (Home), pokébola
  vermelha = abrir o pack (`/pack`), que enche de baixo pra cima conforme
  o cooldown (`packStatus` compartilhado no `HandleInertiaRequests`). O
  ícone Packs e a tela `/packs` saíram em 14/10 (ver pendências).
  Rótulo do dock só com mouse/teclado (no toque ficava preso). Abaixo de
  400px o dock encolhe pra caber os botões.
- **Ajustes vira tela (14/10)**: `/ajustes` (`settings/Show`, no
  `GroupLayout`): cartão do perfil → `/perfil` (a seta do perfil volta
  pra Ajustes), Sons (localStorage `pack:muted`, `lib/soundPreference.ts`,
  o mesmo do pack) e Sair. O menu do dock morreu; **admin não tem atalho
  na UI** (decidido com o usuário: quem é admin digita `/admin`). Tema
  escuro **não existe** nas telas novas (tudo força `.entry-light` e usa
  cores fixas), por isso não tem toggle.
- **Pisca na entrada de telas pesadas**: o `GroupLayout` só começa o
  deslize depois de 2 `requestAnimationFrame`, senão a animação rodava
  enquanto a tela nova travava a thread (perfil mede sprites) e parecia
  um pisca no celular.
- **Cards da Home mais cheios (14/10)**: Coleção mostra o espécime de
  maior valor (`FindBestSpecimen`: shiny > raridade > IV > menor mint)
  como sprite pixelado grande de fundo, inclinado e cortado pela borda
  (pedido do usuário), ou a silhueta do Pikachu se não tem nada; Ranking
  mostra o top 3 (desktop); Trocas mostra recebidas, enviadas e os
  repetidos pra trocar (desktop; o ícone solto foi rejeitado).
- **Onde paramos (14/10, 14h30)**: tudo commitado e enviado no `main`.
  Usuário aprovou a Home (cards, dock, abas deslizando) e pausou.
  Silhueta do card da Coleção em `game.onboarding.home_silhouette_species_id`
  (Charizard). Amigos online: no desktop na linha do dock à esquerda
  (`GroupLayout`), no celular acima do dock (Home).
- **Pendências** (revisadas com o usuário em 14/10, 16h50):
    - Dock apertado no celular: com o Packs fora são 7 botões, conferir
      se ainda aperta.
    - Conferir no Gmail o email real do "esqueci a senha" (disparado pro
      usuário em 14/10, 16h50; o SMTP aceitou).
    - `ResetUnownedAvatar` precisa ser chamado no `AcceptTrade` (fase 4).
    - Fase 4: trocas,
      Reverb, banimento. Fase 5: polimento (giroscópio, apelido no
      espécime).
    - PWA por último (pedido do usuário).
    - **Recusado por agora:** arrastar com o dedo entre abas (não precisa)
      e tema escuro nas telas novas (ainda não).
    - **Ideia futura do usuário:** moeda ou "dust" pra comprar packs. O
      `SPEC.md` põe moeda fora de escopo: se voltar, atualizar o SPEC
      antes de qualquer código. Por isso o ícone e a tela `/packs`
      ("Conteúdo futuro") saíram do dock em 14/10.
    - Conta de teste (`claudetest@512app.com` / `claude`) sem dados,
      avatar Pikachu, pack disponível.
- **Verificar (14/10, mergeado e enviado no `main`)**: `/verificar` (Ajustes → "Verificar um pack", ou
  "Conferir de novo na verificação" no Comprovante, que já preenche via
  query). Calculadora pura: `POST /verificar` (JSON) roda o mesmo
  `DrawPack` + `ResolveSpecimen` da abertura sem gravar nada
  (`VerifyPack`); hash opcional (sem ele não diz se bate). Não confronta
  com o banco (decidido com o usuário). Mint não aparece (não sai da
  seed). As espécies por tier saíram do `OpenPack` pra
  `ListSpeciesIdsByTier`, usada pelos dois. Client seed aceita até 255
  na verificação (a gerada passa dos 24 da edição).
- **Trabalho em dupla com o Kimi K3 (14/10)**: Kimi Code CLI 2.2.0
  instalado (`npm i -g @moonshot-ai/kimi-code`), login OAuth no plano do
  usuário (kimi.ai). Modelo: `-m kimi-code/k3` (o padrão do CLI é o K2.8
  Preview). O Claude coordena: divide a pendência, mostra pro usuário quem
  faz o quê, cria a worktree com `scripts/kimi-worktree.sh <branch> [base]`
  (em `../512app-worktrees/`, vendor instalado, node_modules/Vite em
  symlink, `.env` copiado) e chama
  `cd <worktree> && KIMI_CODE_TRUST_WORKSPACE=1 kimi -m kimi-code/k3 -p "..."`.
  O Kimi lê o `AGENTS.md` sozinho (que manda ler este arquivo e o SPEC).
  `-p` aprova tudo sozinho, por isso o Kimi só trabalha na worktree. Tudo
  dele passa pela revisão do Claude e pelo `composer ci:check` antes de
  juntar.
- **Fase 3 pronta (14/10, primeira em dupla com o Kimi)**: spec em
  `docs/superpowers/specs/2026-10-14-fase-3-social-design.md`, plano em
  `docs/superpowers/plans/2026-10-14-fase-3-social.md`.
    - **Rankings (Kimi)**: `/ranking?por=especies|shinies|iv|primeiros`
      (`RankingTab`, `BuildRanking` + uma Action por aba em
      `app/Actions/Ranking/`, posição pelo `RankPositions`). "Menor mint"
      do SPEC virou **Primeiros** (quantos mint #1,
      `game.ranking.first_mint`). A Home continua no `BuildSpeciesRanking`.
    - **Cartão de Treinador**: `/treinador/{user}` (`BuildTrainerCard`):
      números, Pokédex, amizade (adicionar/aceitar pelos endpoints que já
      existiam), Time (`FindTopSpecimens`, `game.profile.team_size` 6;
      o `FindBestSpecimen` da Home usa ele) e coleção. Grade e ficha da
      Coleção viraram `PokedexGrid` e `useSpecimenSheet`; a ficha ganhou o
      slot `actions`.
    - **Comparar**: `/comparar/{mine}/{theirs}` (`BuildComparison` valida e
      dá 404; `CompareSpecimens` puro decide quem ganha). Botão "Comparar
      com o meu" na ficha do cartão de outro jogador.
    - Ranking, Amigos e Feed (`trainerId`) linkam pro cartão; `/perfil`
      tem "Ver meu Cartão de Treinador".
    - Conta de teste agora tem 6 packs abertos (30 espécimes) pra testar.
- **Lições da dupla com o Kimi**: o K3 seguiu o plano à risca, rodou TDD
  e o CI e relatou as decisões. Plano com código pronto funciona bem pra
  ele. Tipo genérico de PHPStan com interseção (`T&array{...}`) não
  resolve: não pôr isso em plano.
- **Ajustes do teste do usuário (14/10, 17h40)**: cerimônia do pack com os
  valores de Tamanho/IV/Natureza em branco e blocos sólidos (herdavam a
  cor escura do `.entry-light` e eram transparentes). Filtro da coleção
  no cartão de outro: "Tem / Não tem" (`PokedexGrid` `ofOther`). Recusar
  e cancelar pedido de amizade pedem Sim/Não, com a linha inteira virando
  a pergunta. Dock ocupa a largura do conteúdo no celular, botões de 48px
  (desktop igual, por causa dos amigos online na mesma linha). "Shinies"
  fica (plural usado pela comunidade).
- **Onde paramos (14/10, 17h50)**: tudo commitado e enviado no `main`.
  Usuário testou a fase 3 no navegador (rankings, feed, verificar ok) e
  pausou. Falta ele testar o Comparar (precisa de uma espécie em comum
  com o Claude#9805: liberar pack em `/admin/jogadores` → ⋯). Próximo
  passo combinado: Fase 4 (trocas), com brainstorm antes e dividindo com
  o Kimi de novo.
- **Ajustes de 14/10, noite (tudo commitado e enviado no `main`)**:
    - Pedidos de amizade (recebidos e enviados) abrem o Cartão de
      Treinador; filtro **"Em comum"** no cartão de outro jogador
      (`viewerSpeciesIds` do `BuildTrainerCard`). Testado pelo usuário.
    - Natureza: nome dos jogos em inglês + efeito como curiosidade
      (`Nature::effect()`, "+Def −AtqE" ou "Neutra"); no Comparar, nota
      "Tamanho e natureza não contam pontos". Siglas dos atributos em
      português: HP · Atq · Def · AtqE · DefE · Vel (`STATS` no `meta.ts`).
    - **Regra global de transição do tema estava fora de `@layer`** e
      anulava todo `transition-transform` das utilities: foi pro
      `@layer base`. CSS novo sem layer ganha das utilities do Tailwind 4.
    - Cartas da coleção e ficha clara no tratamento de carta real
      (`rarityLook().card`, classes `card-foil-*` no `app.css`): comum
      fosca, incomum chapada, raro holo, lendário toda dourada, shiny
      escura "full art" com arco-íris. Selo "Nº 1" (o usuário acha feio,
      mas aceitou). Inclinação com o mouse só no desktop (`v-card-tilt`).
    - `v-pixel-fit` centraliza o GIF pelo desenho (Mewtwo tinha o quadro
      largo pro rabo). O rabo descendo é da animação oficial.
    - Tile do pack na Home enche como a pokébola do dock
      (`usePackProgress`), com onda; pokébola com sombra. Blocos da Home
      continuam quadrados (6:5 foi testado e recusado: passava da tela).
    - Dock cabe a 401–440px (`max-[440px]:size-11`).
    - Abertura: as 512 pokébolas descem num redemoinho
      (`useDotField().swirlInto`) em vez de a grade encolher junta.
    - Kimi: usar só até ~95% do plano; o usuário avisou que está quase
      no fim (14/10). Sem saber o %, não delegar.
- **Pendências (14/10, noite)**: Fase 4 (trocas, Reverb, histórico de
  donos, `ResetUnownedAvatar` no `AcceptTrade`, banimento), Fase 5
  (giroscópio, apelido no espécime), PWA por último. Selo Nº 1 talvez
  repensar. Comparar e "esqueci a senha" já conferidos pelo usuário.
- **Ideias futuras aprovadas pelo usuário (14/10, noite)** — nenhuma
  entra sem atualizar o `SPEC.md` antes (moeda está fora de escopo hoje):
    - **Dust só dentro do jogo**: repetido vira dust, dust compra pack.
      Repetido precisa valer mais trocado do que moído.
    - **Loja de cosméticos não aleatórios** (moldura de perfil, verso de
      carta, selo de apoiador). Nada de pack pago nem caixa aleatória.
    - **Dinheiro real**: não na versão com Pokémon (marca da Nintendo/TPC;
      pack pago aleatório + trocas ≈ caixa de recompensa, ECA Digital).
      Se for monetizar de verdade, versão com criaturas próprias, com
      advogado antes. Trend no Instagram só depois dessa decisão.
- **Fase 4A, trocas (pronta 15/10, mergeada e enviada no `main`)**: spec
  `docs/superpowers/specs/2026-10-14-trocas-design.md`, plano
  `docs/superpowers/plans/2026-10-14-trocas.md`. Backend pelo Kimi
  (Actions em `app/Actions/Trades/`, `specimen_transfers`, montador,
  histórico, feed), front pelo Claude (`/trocas`, `components/trade/*`,
  montador `trades/Create`, cerimônia `lib/trade/director.ts` +
  `TradeCeremony`). `AcceptTrade` já chama o `ResetUnownedAvatar` pros
  dois lados.
    - **Ficha nas trocas**: `GET especime/{specimen}` (`ShowSpecimenController`,
      JSON de um espécime só, porque depois da troca o dono mudou; a
      montagem saiu pra `PresentSpecimenSheet`). Caixa: tocar na carta
      abre; montador: toque escolhe, o "i" do canto abre; resumo da
      cerimônia: abre dentro dela (o `PackModal` z-50 ficaria atrás).
    - Cartas da troca com o selo da raridade (como a coleção).
    - **Cerimônia escala** (`ceremonyLevel`: comum 0 → shiny 4): do raro
      pra cima carga antes de cruzar, halo e partículas (`FxCanvas` do
      pack); lendário e shiny com faíscas puxadas, flash de tela, raios,
      chuva, tremida e raios girando atrás da carta no resumo. **Sem o
      ronco** do pack (o usuário achou "motor estranho"; também ficava
      ligado pra sempre).
    - **Dock conta tudo** (`tradeAlerts` compartilhado): recebidas abertas
      + desfechos que quem propôs não viu (`Trade::outcomeUnseenBy`:
      aceita até a cerimônia tocar; recusada/caída até abrir a aba delas,
      `MarkTradeOutcomesSeen`). Abas com contador e selo "Nova".
      `pendingTrades` continua só recebidas (Home).
    - Ficha: histórico usa as cores fixas do `tone` (com `text-ink` sumia
      no tema escuro do celular, fora do `.entry-light`).
    - Teste no navegador: Playwright com rota interceptada (trocar
      raridade só na resposta) precisa de
      `--disable-features=...LocalNetworkAccessChecks` senão o Vite é
      bloqueado. Conta Claude ganhou 5 packs (saiu um Zapdos, trocado
      com a Lin na troca 6).
    - Próximo: **4B** (Reverb + notificação animada com som; dividir com
      o Kimi se o plano dele tiver folga: eventos no servidor com ele,
      notificação com o Claude), depois **4C** (banimento).
