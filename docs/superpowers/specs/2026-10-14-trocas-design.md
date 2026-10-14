# Fase 4A, as trocas — design

Data: 14/10, noite. Decisões tomadas com o usuário, uma a uma, no
brainstorm. Feita só pelo Claude: o plano do Kimi está no fim.

Itens do `SPEC.md`, seção 8, fase 4, que esta parte cobre:

- Propor, aceitar, recusar
- Histórico de donos anteriores de cada espécime

A fase 4 foi dividida em três partes, cada uma com spec e plano próprios:

| Parte | O quê | Depende de |
|---|---|---|
| **4A** (este spec) | Trocas e histórico de donos | nada |
| 4B | Tempo real com Reverb: aviso de troca **bem bonito, animado e com som** (pedido do usuário) e fim da recarga de 60s da presença | 4A |
| 4C | Banimento (temporário ou permanente, email com o motivo) | nada |

Fora da 4A: presente (dar sem receber) e pedir sem oferecer, negociação
em várias rodadas na mesma troca, aviso em tempo real (4B), banimento
(4C), dust e loja (ideias futuras, exigem mudar o `SPEC.md`).

---

## Decisões

| Pergunta | Decisão |
|---|---|
| Com quem dá pra trocar | Só com amigos (amizade aceita) |
| Formato | Vários por vários, 1 a 3 espécimes de cada lado |
| Espécime em mais de uma proposta | Pode; ao aceitar uma, as outras pendentes com algum desses espécimes caem com motivo "não disponível" |
| Contraproposta | Simples: recusa a original e cria uma nova na direção contrária, ligada por `counter_of_id` |
| Validade | 72h (`game.trades.expires_after_hours`), com contagem visual no card |
| Histórico de donos | Linha do tempo na ficha de qualquer espécime + ícone "veio de troca" na carta da coleção |
| Montar a proposta | Tela própria `/trocas/nova/{amigo}`, com três entradas |
| Momento da troca | Cerimônia curta + círculo branco revelando o resumo (como o fim do pack) |
| Feed | Só trocas que movem shiny ou tier de `game.group.feed_highlight_tiers` |

---

## Dados

### `trades` (já existe; colunas novas)

| Coluna | Tipo | Para quê |
|---|---|---|
| `expires_at` | timestamp | Criação + `expires_after_hours` |
| `counter_of_id` | fk `trades`, nullable | Proposta que esta contrapõe |
| `cancel_reason` | enum nullable: `by_proposer` \| `unavailable` | Por que foi cancelada; a tela explica |
| `proposer_seen_at` | timestamp nullable | Quem propôs já viu a cerimônia da troca aceita |

O `status` continua com `pending | accepted | declined | cancelled`.
**"Expirada" é calculada**: `pending` com `expires_at <= now`. Nenhuma
tarefa agendada; aceitar, recusar ou contrapropor uma vencida é barrado
na mesma checagem. Um scope `Trade::open()` (pendente e não vencida) e um
`isExpired()` no model concentram isso.

Novo enum `TradeCancelReason` (`by_proposer`, `unavailable`).

### `trade_items`

Fica como está: `trade_id`, `specimen_id`, `side` (`offered` |
`requested`). `offered` é sempre de quem propôs, `requested` sempre do
destinatário.

### `specimen_transfers` (nova)

| Coluna | Tipo |
|---|---|
| `id` | |
| `specimen_id` | fk `specimens` |
| `from_user_id` | fk `users` |
| `to_user_id` | fk `users` |
| `trade_id` | fk `trades` |
| `transferred_at` | timestamp |

Índice em `(specimen_id, transferred_at)`. Só guarda trocas: a primeira
linha da linha do tempo ("Capturado por X") vem do próprio espécime (dono
da `opening` e `caught_at`). Migrations com `down()`.

### `config/game.php`, bloco `trades`

```php
'trades' => [
    'max_items_per_side' => 3,
    'expires_after_hours' => 72,
    'max_pending_outgoing' => 10,
    'message_max_length' => 140,
    // Enviadas que venceram ou caíram somem da lista depois disso.
    'closed_visible_days' => 7,
],
```

### Regras que valem em todo lugar

- Espécime de bola fechada não entra em troca (o `RevealedScope` já o
  esconde; a validação confere de novo).
- `#0001`, "Primeiro do servidor" e "quem abriu" no feed não mudam com a
  troca: a posse muda, a história não.

---

## Regras (Actions em `app/Actions/Trades/`)

Controllers finos: FormRequest valida o formato, a Action aplica a regra
de negócio e lança `ValidationException` com mensagem em português quando
a regra barra.

### `ProposeTrade::handle(User $proposer, User $friend, array $offeredIds, array $requestedIds, ?string $message): Trade`

- `$proposer` e `$friend` são amigos (amizade aceita) e diferentes.
- Cada lado tem de 1 a `max_items_per_side` ids, sem repetição entre e
  dentro dos lados.
- Todos os `offered` são do `$proposer`, todos os `requested` do
  `$friend`, todos revelados.
- O `$proposer` tem menos de `max_pending_outgoing` propostas `open()`
  enviadas.
- Não existe proposta `open()` idêntica (mesmo par, mesmos dois conjuntos
  de ids): "Você já propôs essa troca".
- Mensagem opcional, até `message_max_length`, sem espaços nas pontas.
- Cria `trades` (`pending`, `expires_at`) e os `trade_items` numa
  transação.

### `AcceptTrade::handle(Trade $trade, User $accepter): Trade`

Numa transação:

1. `lockForUpdate` na troca; ela precisa estar `open()` e `to_user_id`
   ser o `$accepter`. Senão: "Essa proposta não está mais pendente".
2. `lockForUpdate` nos espécimes, **ordenados por id** (evita deadlock).
   Cada `offered` ainda é do proponente e cada `requested` ainda é do
   destinatário. Se algum mudou: a troca vira `cancelled` /
   `unavailable` e o aceite falha com "Algum espécime dessa troca não está
   mais disponível".
3. Troca o `user_id` de cada espécime e grava uma `specimen_transfers`
   por espécime.
4. Toda outra troca `pending` com item em algum desses espécimes vira
   `cancelled` / `unavailable`.
5. `ResetUnownedAvatar` pros dois jogadores.
6. A troca vira `accepted`, `responded_at = now`.

### `DeclineTrade::handle(Trade $trade, User $user)`

Só o destinatário, troca `open()`. Vira `declined`, `responded_at`.

### `CancelTrade::handle(Trade $trade, User $user)`

Só quem propôs, troca `pending` (mesmo vencida, pra limpar). Vira
`cancelled` / `by_proposer`.

### `CounterTrade::handle(Trade $original, User $user, array $offeredIds, array $requestedIds, ?string $message): Trade`

Só o destinatário da original, que precisa estar `open()`. Numa
transação: recusa a original (`declined`) e chama `ProposeTrade` com o
destinatário como novo proponente, gravando `counter_of_id`. As
validações são as mesmas; se falharem, a original continua pendente.

### `MarkTradeSeen::handle(Trade $trade, User $user)`

Só quem propôs, troca `accepted`: grava `proposer_seen_at` se for nulo.

**Corrida aceitar × cancelar:** as duas travam a troca; a primeira vence,
a segunda recebe "Essa proposta não está mais pendente".

---

## Telas

Todas atrás de `auth` + `onboarded`, dentro do `GroupLayout`, layout
crítico em Tailwind, conferidas a 412px. Nenhum sorteio nem decisão no
cliente.

### `/trocas` (deixa de ser só leitura)

Abas **Recebidas · Enviadas · Concluídas** (query `?aba=`, montado no
servidor por um `BuildTradeInbox` reescrito).

- Card da proposta: avatar e nome do amigo (link pro cartão), mensagem,
  selo "Contraproposta" quando `counter_of_id`, mesa em miniatura
  "Você dá ⇄ Você recebe" com cartinhas no tratamento de raridade
  (`rarityLook`), tocar numa carta abre a ficha.
- Validade: "vence em 2d 14h" + barra esvaziando; nas últimas 24h
  hh:mm:ss e vermelho. O servidor manda `expiresAt`; o cliente só conta.
- Recebidas: **Aceitar**, **Contrapropor**, **Recusar** (Sim/Não).
- Enviadas: **Cancelar** (Sim/Não). Vencidas e caídas aparecem
  esmaecidas com o motivo ("expirou", "um espécime foi trocado") por
  `closed_visible_days`.
- Concluídas: aceitas e recusadas dos últimos `closed_visible_days`;
  tocar numa aceita reabre o resumo.
- Botão **Nova troca** → escolher o amigo → montador.
- Se houver troca aceita com `proposer_seen_at` nulo do jogador, a tela
  abre com a cerimônia dela.

### `/trocas/nova/{amigo}` (montador; contraproposta reaproveita)

- 404 se não for amigo. Contraproposta: `/trocas/{trade}/contrapropor`,
  mesma tela já preenchida com a original invertida.
- **Mesa** (fixa no topo no celular): "Você dá" e "Você recebe", 3 vagas
  cada; tocar numa carta da mesa tira.
- **Coleção** embaixo, alternando "Meus" / "De {nick}", filtros Todos ·
  Repetidos · Faltam pra mim · Em comum. A grade é **por espécime**
  (cada Ponyta separado, com mint e IV), não por espécie. Dados vêm do
  servidor (`BuildTradeBuilder`).
- Query `?pedir={specimen}` pré-seleciona em "Você recebe".
- Mensagem opcional; **Propor troca** só acende com 1+ de cada lado.

### Pontos de entrada e o resto do app

- Cartão de Treinador de um amigo: **Propor troca**.
- Ficha de um espécime do amigo: **Pedir este** (montador com ele
  pré-selecionado), no slot `actions` que já existe.
- Ficha de qualquer espécime: seção **Histórico** (linha do tempo:
  capturado por, depois cada troca com data). Vem no JSON da ficha.
- Carta da coleção: ícone pequeno de "veio de troca" (o espécime de
  melhor valor da espécie tem `specimen_transfers`).
- Dock: contador de propostas recebidas `open()` no ícone de Trocas
  (prop compartilhada no `HandleInertiaRequests`, como amizade).
- Home: tile de Trocas continua, agora com dado real.
- Feed: item novo "Lin e Claude trocaram · Blastoise ⇄ Venusaur + Ekans"
  quando algum espécime da troca é shiny ou de tier em
  `feed_highlight_tiers`. `BuildGroupFeed` junta aberturas e trocas por
  data.
- Até a 4B, `/trocas` e o contador do dock recarregam com o `usePoll` de
  `game.group.presence_poll_seconds`.

---

## Cerimônia

`components/trade/TradeCeremony.vue` + roteiro em `lib/trade/director.ts`
(WAAPI, sem lib, sons do `lib/pack/sfx.ts`, preferência de som de
`lib/soundPreference.ts`).

**Quando toca:** pra quem aceita, logo depois do aceite confirmado; pra
quem propôs, ao abrir uma troca aceita com `proposer_seen_at` nulo (e aí
chama `MarkTradeSeen`). Quem recebeu o quê vem pronto do servidor.

**Roteiro** (~2,5s até o resumo; um toque pula pro resumo):

1. Mesa: cartas dos dois lados frente a frente, pulsando.
2. Os dois montes cruzam em arco girando 180°, encontram-se no meio com
   flash e som.
3. As recebidas pousam do seu lado e brilham conforme a raridade.
4. Círculo branco cresce a partir da mesa (o mesmo `clip-path` do fim do
   pack) e revela o resumo.

**Resumo:** "Troca feita com {nick}" + avatar; **Você recebeu** em
destaque (cartas no tratamento da coleção, cascata, tilt no desktop, selo
**Nova na Pokédex!** quando a espécie não existia na coleção antes da
troca, calculado no servidor); **Você deu** menor e esmaecido; botões
**Ver na coleção** e **Voltar pras trocas**.

Movimento reduzido: direto pro resumo. Som desligado: silêncio.

---

## Testes (Pest)

- **`ProposeTrade`**: só amigos; 1–3 por lado; posse dos dois lados;
  espécime fechado barrado; repetido barrado; teto de pendentes;
  duplicata exata barrada; `expires_at` certo.
- **`AcceptTrade`**: troca a posse e grava o histórico; cancela as outras
  com `unavailable`; reseta avatar dos dois lados; barra vencida,
  alheia, já respondida e espécime que mudou de dono (a troca cai com
  `unavailable`); duas trocas disputando o mesmo espécime: só a primeira
  passa (sequencial; o lock real não se simula no SQLite).
- **Recusar / cancelar / contrapropor**: permissão e estado; a
  contraproposta recusa a original e cria a inversa com `counter_of_id`;
  falha na validação deixa a original pendente.
- **Expiração calculada**: some do `open()` depois de 72h.
- **Feed**: só trocas com shiny ou tier de destaque.
- **HTTP**: `/trocas` (abas, props), montador (404 pra não amigo,
  `?pedir=`), contraproposta, ficha com histórico, contador do dock.

## Verificação

- `composer ci:check` verde.
- Prints a 412px e desktop do montador, de `/trocas` com contagem, da
  cerimônia e do resumo, com uma troca real entre a conta de teste
  (`claudetest@512app.com`) e a do usuário.

## Entrega

Branch `trocas` a partir do `main`. Ordem: dados e Actions (TDD) →
`/trocas` → montador → histórico na ficha, carta, dock e feed →
cerimônia por último.
