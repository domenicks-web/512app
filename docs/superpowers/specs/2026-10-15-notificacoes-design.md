# Fase 4B: notificações em tempo real (Reverb)

Decidido com o usuário em 15/10/2026. Ele aprovou o design em três partes no
chat e delegou o resto ("vc e kimi façam tudo").

## Objetivo

Quando algo acontece com o jogador (trocas, amizade) ou no grupo (lendário ou
shiny no feed), ele fica sabendo na hora, em qualquer tela com dock, por uma
bolha animada com som que sai do ícone certo do dock. O contador do dock e as
listas abertas atualizam na hora. Isso substitui o poll de 60s de Trocas e
Amigos (a presença online continua no poll).

## Fora de escopo

- Presença online em tempo real (continua `usePoll` de
  `game.group.presence_poll_seconds`).
- Push com o app fechado (fica pro PWA).
- Banimento (4C).

## Eventos

| Tipo (`kind`)       | Quem recebe                               | Disparado em |
|---------------------|-------------------------------------------|--------------|
| `trade_received`    | quem recebe a proposta ou contraproposta  | `ProposeTrade` (cobre o `CounterTrade`, que chama ele) |
| `trade_accepted`    | quem propôs                               | `AcceptTrade` (sucesso) |
| `trade_declined`    | quem propôs                               | `DeclineTrade` |
| `trade_fallen`      | quem propôs cada troca que caiu           | `AcceptTrade` (as outras pendentes com os mesmos espécimes, e a própria quando o aceite falha por espécime indisponível) |
| `friend_request`    | quem recebe o pedido                      | `SendFriendRequest` (pedido novo) |
| `friend_accepted`   | quem mandou o pedido                      | `AcceptFriendRequest` (inclusive pelo pedido cruzado do `SendFriendRequest`) |
| `feed_highlight`    | o grupo inteiro, menos quem abriu         | `RevealSlot`, quando a bola revela agora um shiny ou tier de `game.group.feed_highlight_tiers` |

Não notificam: cancelamento pela própria pessoa, proposta que expirou e a
amizade automática com quem convidou (`BefriendInviter`).

## Arquitetura

- **Laravel Reverb** (o SPEC já previa). `BROADCAST_CONNECTION=reverb` no
  local; `null` nos testes e no CI.
- **Canais privados**: `users.{id}` (só o próprio) e `group` (qualquer
  usuário com `hasCompletedProfile()`), em `routes/channels.php`.
- **Um evento só**: `App\Events\PlayerNotified implements ShouldBroadcastNow,
  ShouldDispatchAfterCommit`. Ele carrega uma `App\Data\Notice` e um canal
  (`users.{id}` ou `group`). O nome no broadcast é `notice`
  (`broadcastAs`), e o payload é a `Notice` serializada.
  `ShouldBroadcastNow` evita depender do worker da fila; `AfterCommit`
  garante que só sai depois que a transação gravou.
- **`App\Data\Notice`** (readonly, `toArray()`), pronta no servidor. O front
  não decide nada:
  - `kind`: um dos tipos da tabela.
  - `dock`: `'trades' | 'friends' | 'home'` (de qual ícone a bolha sai).
  - `title`: linha em negrito ("Lin quer trocar").
  - `body`: linha menor ("Zapdos ⇄ Nidorina"), ou `null`.
  - `spritePath`: caminho no storage (avatar de quem agiu ou o espécime), ou `null`.
  - `tier` e `isShiny`: só no `feed_highlight` e no `trade_received` (melhor
    espécime oferecido), pra moldura e som; `null`/`false` nos outros.
  - `href`: URL de destino (Wayfinder no servidor: `route(...)`).
  - `reload`: lista de props Inertia que a página atual deve recarregar
    (`['tradeAlerts', 'received', 'sent', 'done', 'newCounts']`,
    `['pendingFriendRequests', 'friends', 'incoming', 'outgoing']`,
    `['feedHighlight']`). O front só recarrega as que a página tem.
- **`App\Actions\Notify\NotifyPlayer`** e **`NotifyGroup`**: montam a `Notice`
  de cada tipo (um método por tipo, ex. `tradeReceived(Trade)`) e despacham
  o evento. As Actions de negócio só chamam essas duas, sem montar texto.
- **Dev**: o `php artisan dev` sobe `reverb:start --host=0.0.0.0 --port=8080`
  (`DevCommands::artisan`, só no ambiente local). O Echo conecta em
  `VITE_REVERB_HOST`, que no `.env` local é o `DEV_LAN_HOST` (o S26+ conecta
  pelo IP, como no Vite). `ws://` no http por IP.
- **Produção**: o Reverb vira um processo próprio (supervisor ou systemd) atrás
  do proxy, em `wss://`. Fica anotado no SPEC; sem deploy agora.

## Front

- **`laravel-echo` + `pusher-js`**, configurados em `resources/js/lib/echo.ts`
  (só no cliente, nunca no SSR).
- **`composables/useRealtime.ts`**, usado no `GroupLayout` (persistente, então
  conecta uma vez): assina `users.{id}` e `group`, empurra cada `Notice` numa
  fila e chama `router.reload({ only })` com as props de `reload` que a página
  atual tem.
- **Bolha** (`components/group/DockBubble.vue`):
  - Nasce do ícone `dock` da `Notice` com mola (`springs` do `motion.ts`),
    com um rabinho pro ícone, presa dentro da tela. O ícone pula.
  - Sprite à esquerda (`FittedSprite`), `title` em negrito e `body` menor. No
    `feed_highlight`, a moldura é a da raridade (`rarityLook`).
  - Fica uns 4,5s; tocar e segurar pausa; arrastar pra baixo dispensa; tocar
    navega pro `href`.
  - Fila: uma bolha por vez com um "+N" no canto; mais de 3 do mesmo `dock`
    na fila viram uma bolha só ("3 novidades nas trocas").
  - `role="status"`, `aria-live="polite"`. Com movimento reduzido só faz
    fade, e o ícone não pula.
- **Som**: um método novo `Sfx.notice(kind, tier, shiny)`, sintetizado, sem
  arquivo. É um "plim" de duas notas que sobe no aceito, desce no recusado ou
  caído e toca o `chord` da raridade no `feed_highlight`. Respeita o
  `isSoundMuted()` (Ajustes → Sons). O celular vibra 20ms no
  `trade_received` e no `trade_accepted`.
- **Sem dock** (tela do pack, admin): o `GroupLayout` não está montado, então
  nada aparece. Os contadores já vêm certos do banco quando o jogador voltar.
  Na tela do pack não pode sair som nem bolha no meio da abertura.
- **Poll**: sai o `usePoll` de Trocas (`trades/Index`). Em Amigos e na Home
  fica só a presença (`only: ['friendsOnline']` / `['friends']`).

## Erros

- Reverb fora do ar: o Echo tenta reconectar sozinho. Nada se perde, porque
  contador e listas vêm do banco a cada carga de página.
- Falha ao transmitir (Reverb parado) não pode quebrar a ação de negócio:
  `PlayerNotified` só sai depois do commit, e um erro de broadcast vai pro
  log (`report()`), sem exceção pro jogador.

## Testes

- **Pest** (Kimi): para cada linha da tabela, `Event::fake([PlayerNotified::class])`
  e a asserção do canal, `kind`, `href` e `dock`. Os casos negativos também:
  cancelamento próprio, `BefriendInviter` e quem abriu o lendário não
  recebem. Autorização dos canais: `users.{id}` só pro dono e `group` só
  com perfil completo. E o broadcast quebrado não derruba a Action.
- **Front** (Claude): `vue-tsc` e `vp check`. Fluxo real com duas sessões no
  Playwright (uma propõe, a outra vê a bolha). Depois o usuário confere no
  S26+.

## Divisão

1. **Kimi**, na worktree `../512app-worktrees/notificacoes-back`: Reverb,
   canais, `Notice`, `PlayerNotified`, `NotifyPlayer` e `NotifyGroup`, os
   disparos e os testes.
2. **Claude**, em paralelo, no branch `notificacoes`: Echo, `useRealtime`,
   `DockBubble`, `Sfx.notice` e a troca dos polls, testando com
   `Notice` falsa enquanto o servidor não chega.
3. **Claude**: juntar, revisar o código do Kimi, `composer ci:check`, teste
   com duas sessões, SPEC e checkpoint.
