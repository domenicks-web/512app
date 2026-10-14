# Fase 3, o social — design

Data: 14/10. Decisões tomadas pelo Claude com aval do usuário ("faça o que
você concorda, com base em jogos Pokémon"). Primeira fase feita em dupla
com o Kimi K3 (ver `AGENTS.md` e o checkpoint do `CLAUDE.md`).

Itens do `SPEC.md`, seção 8, fase 3:

- Perfil público de cada jogador
- Comparar dois espécimes da mesma espécie lado a lado
- Ranking: mais espécies, mais shinies, maior IV, menor mint

Fora de escopo aqui: insígnias/conquistas, perfil fora do grupo (o app é
todo atrás de convite), editar o próprio perfil (já existe em `/perfil`).

## Divisão do trabalho

| Parte | Quem | Branch | Depende de |
|---|---|---|---|
| A. Rankings novos | Kimi | `fase3-rankings` | nada |
| B. Cartão de Treinador (perfil público) | Claude | `fase3-perfil` | nada |
| C. Comparar espécimes | Claude | `fase3-perfil` | B |
| D. Costura: links pro perfil no Ranking, Amigos e Feed | Claude | `main` depois de A e B | A e B |

A e B correm em paralelo. Pra não ter conflito, **o Kimi é o único que
mexe em `ranking/Show.vue` e no backend do ranking**; o link da linha do
ranking pro perfil entra só na parte D, depois do merge do A.

---

## A. Rankings novos (Kimi)

A tela `/ranking` ganha quatro abas, na ordem: **Espécies** (o que já
existe), **Shinies**, **Maior IV**, **Primeiros**. A aba vem da query
`?por=especies|shinies|iv|primeiros` (padrão `especies`, valor inválido cai
no padrão) e quem monta é o servidor; a troca de aba é uma visita Inertia
com `preserveState`, sem cálculo no cliente.

Regras comuns às quatro (iguais às do ranking atual):

- Entra todo jogador com perfil completo (`nickname` não nulo).
- Empate no critério principal divide a posição (1º, 1º, 3º).
- Quem tem 0 no critério fica sem posição (`null`), no fim da lista.
- Só conta espécime revelado (o `RevealedScope` global já faz isso).
- A linha de quem está vendo vem marcada, como hoje.

Critérios:

| Aba | Critério principal | Desempate da ordem (não da posição) | Valor mostrado na linha |
|---|---|---|---|
| Espécies | espécies distintas | shinies | "87 espécies" (como hoje) |
| Shinies | espécimes shiny | espécies distintas | "3 shinies" |
| Maior IV | maior `iv_total` de um espécime só | menor mint desse espécime | "IV 171" + sprite pequeno do espécime e nome da espécie |
| Primeiros | espécimes com `mint_number` = 1 ("primeiro do servidor") | espécies distintas | "4 primeiros" |

**Por que "Primeiros" e não "menor mint":** com 151 espécies, quase todo
mundo vai ter algum mint #1 cedo, então "o menor mint de cada um" empata
todo mundo em 1. Contar quantos mint #1 a pessoa tem é a mesma ideia (mint
baixo vale mais) e separa os jogadores. O selo "Primeiro X do servidor" já
existe na cerimônia do pack, então o nome conversa com o jogo.

Backend:

- Uma Action por critério, no padrão do `BuildSpeciesRanking`
  (`BuildShinyRanking`, `BuildIvRanking`, `BuildFirstMintRanking`), todas
  devolvendo a mesma forma de linha: `userId`, `nickname`, `tag`,
  `avatarSpritePath`, `position`, `value` (int) e, só na de IV,
  `specimen` (`speciesName`, `spritePath`, `isShiny`, `mintNumber`).
- O `ShowRankingController` escolhe a Action pela aba e manda `rows`,
  `viewerId` e `tab`. O `BuildSpeciesRanking` continua com o formato que a
  Home usa (top 3 e posição do jogador): **não quebrar a Home**.
- Um enum `RankingTab` com os quatro valores.
- O valor do mint "primeiro" (1) vai em `config('game.ranking.first_mint')`.

Front: abas no topo da tela (pílulas, no estilo do filtro da Coleção:
Todas/Tenho/Faltam), lista igual à de hoje. O subtítulo diz a posição do
jogador naquela aba ("Você está em 2º em shinies") ou como entrar ("Abra
um pack pra entrar no ranking"; na de Primeiros, "Seja o primeiro a abrir
uma espécie pra entrar").

Testes (Pest): para cada critério, ordem, empate dividindo posição, zero
sem posição, espécime fechado não conta; a aba inválida cai em Espécies; a
Home continua recebendo o mesmo formato.

---

## B. Cartão de Treinador (perfil público, Claude)

Inspirado no Trainer Card dos jogos: um cartão com quem é o treinador e o
que ele já conquistou, e a Pokédex dele embaixo.

Rota: `GET /treinador/{user}` (id), atrás de `auth` + `onboarded`, no
`GroupLayout` com o `GroupScreen` (a seta volta pra tela de onde veio:
padrão Home). Jogador sem perfil completo dá 404. O próprio jogador pode
abrir o seu (vê como os outros veem, com um aviso "Este é o seu cartão" e
link pra `/perfil`).

Conteúdo, de cima pra baixo:

1. **Cartão:** avatar (com o shiny, se for), `nickname#tag`, "Treinador
   desde set/2026" (mês de `created_at`), e três números: posição no
   ranking de espécies, shinies e packs abertos.
2. **Pokédex:** barra de progresso "87 de 151" (como a contagem da Pokédex
   dos jogos).
3. **Amizade:** botão conforme o estado: "Adicionar amigo" (usa o
   `SendFriendRequest` que já existe), "Pedido enviado", "Aceitar pedido"
   (usa o `AcceptFriendRequest`) ou só um selo "Amigos". Some no próprio
   cartão.
4. **Time:** os 6 melhores espécimes (como o time de 6 dos jogos), na
   ordem do `FindBestSpecimen` (shiny > raridade > IV > menor mint). O
   `FindBestSpecimen` ganha um irmão `FindTopSpecimens(User, int $limit)`
   e o atual passa a usar ele com limite 1, pra a ordem ser uma só. Limite
   em `config('game.profile.team_size')` (6). Sem espécime, o bloco some.
5. **Coleção:** as 151 em ordem de dex com silhueta pra quem falta,
   reaproveitando o `BuildCollection` e o `CollectionCard` (o
   `BuildCollection` já recebe o `User`; só passa o dono do cartão).
   Mesmo filtro Todas/Tenho/Faltam.

Clicar numa carta do time ou da coleção abre a ficha (a mesma da sua
Coleção, com "1 de N") com os espécimes **do dono do cartão**:
`GET /treinador/{user}/colecao/{species}` em JSON, reaproveitando o
`BuildSpeciesSpecimens`. A ficha ganha o botão **"Comparar com o meu"**
quando quem vê tem algum espécime daquela espécie (parte C).

Backend: Action `BuildTrainerCard(User $owner, User $viewer)` junta tudo
(ranking via `BuildSpeciesRanking`, contagem de `openings`, estado da
amizade, time, coleção). Controller fino.

Testes: 404 pra perfil incompleto; números certos; estado da amizade nos
quatro casos; time na ordem e com limite; espécime fechado não aparece;
ficha em JSON só com espécimes do dono.

---

## C. Comparar espécimes (Claude)

Rota: `GET /comparar/{mine}/{theirs}` (ids de espécime), Inertia, no
`GroupLayout` (a seta volta pro cartão do dono do `theirs`). 404 se: algum
dos dois não existe ou está fechado, `mine` não é de quem vê, ou as
espécies são diferentes. Os dois podem ser de quem vê (comparar dois seus).

A tela mostra os dois lado a lado, como a tela de status dos jogos:

- No topo, os dois sprites (shiny quando for), dono e número de série
  (mint), com a moldura de raridade da carta.
- Linhas comparando: IV total, os 6 IVs (HP, Atk, Def, SpA, SpD, Spe),
  tamanho, natureza. Em cada linha numérica, o maior ganha destaque
  (verde) e uma setinha; empate fica neutro. Mint: o menor ganha destaque.
- Um placar no fim: "Seu Pikachu ganha em 4 de 7" (os 6 IVs + o total).
  O servidor manda os valores e quem ganha cada linha já decidido
  (`winner: 'mine' | 'theirs' | 'tie'`); o cliente não compara nada.
- Se você tem mais de um espécime daquela espécie, uma fileira de chips
  embaixo troca o `mine` (cada chip é um link pra outra URL de comparação).

Backend:

- `CompareSpecimens::handle(Specimen $mine, Specimen $theirs): array`,
  pura: só lê os dois espécimes recebidos e devolve as linhas com o
  vencedor de cada uma e o placar.
- `BuildComparison::handle(User $viewer, Specimen $mine, Specimen $theirs)`
  confere a regra dos 404 (lança `ModelNotFoundException`, que o Laravel
  já vira 404), chama o `CompareSpecimens` e busca os chips (os outros
  espécimes de quem vê daquela espécie). O controller só chama ela.

Testes: os 404; vencedor de cada linha incluindo empate; placar; chips
listam só os espécimes de quem vê daquela espécie.

---

## D. Costura (Claude, depois do merge de A e B)

- Cada linha do Ranking (as quatro abas) vira link pro cartão.
- Amigos: o nome do amigo vira link pro cartão.
- Feed: "Fulano abriu um X" leva pro cartão de quem abriu.
- Ajustes: o cartão do perfil continua indo pra `/perfil`; dentro do
  `/perfil`, um link "Ver meu Cartão de Treinador".

Ordem das telas no `GroupLayout`: `trainer/Show` e `compare/Show` sem
item no dock (deslizam como tela à direita).

## Verificação

- `composer ci:check` verde em cada branch antes de juntar.
- Revisão do Claude em todo código do Kimi antes do merge (regras do
  `CLAUDE.md`: Action/controller fino, nada de número mágico, vocabulário
  Species/Specimen, nada calculado no cliente).
- Conferir no navegador a 390px e no desktop com dados de verdade: os
  dados do jogo foram zerados, então para testar a tela será preciso
  abrir packs com a conta de teste e com a do usuário (pedir antes de
  abrir na conta do usuário).
