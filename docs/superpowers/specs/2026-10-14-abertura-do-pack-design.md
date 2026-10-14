# Abertura do pack (subprojeto C) — design

Fonte: `design_handoff_512_onboarding/ABERTURA.md` + `512 Abertura.dc.html`
(alta fidelidade) e `SPEC.md` (seções 4–7). Este documento registra só as
decisões de implementação e onde o projeto diverge ou completa o handoff.
As **3 mudanças obrigatórias** do ABERTURA.md têm prioridade sobre o
protótipo e são seguidas à risca. Fora do escopo: modos de roteiro
("Raro + shiny", "Lendário") e o botão "Abrir outro (demo)".

## Regras de produto

- **Cooldown de 24h** contado da última abertura (`users.next_pack_at`),
  como já está em `config('game.pack.cooldown_hours')` e na fase 1 do SPEC.
- **Commit antes, reveal depois.** Cada pack tem a sua `server_seed`. A do
  próximo pack já existe e o hash dela aparece no idle ("Hash do
  servidor"). A seed só é revelada quando as 5 bolas daquele pack forem
  abertas.
- **O sorteio inteiro acontece na explosão** (`POST /pack/abrir`), numa
  transação: cria a `opening` e os 5 `specimens`, com o mint travado.
  Recarregar nunca sorteia de novo.
- **Cada bola só revela o conteúdo quando é clicada**
  (`POST /pack/{opening}/bolas/{slot}`). A resposta da explosão não diz o
  que tem em cada bola, então nem pelo DevTools dá pra espiar a raridade
  antes do clique (mudança obrigatória 1). O servidor marca a bola como
  aberta; recarregar mostra as abertas como sprite e as fechadas
  clicáveis.
- **Espécime de bola fechada não aparece** na coleção, no ranking, no feed
  nem na contagem de espécimes do admin (`specimens.revealed_at`).
  Assim ninguém fica sabendo pelo feed antes do dono abrir.

## Sorteio (servidor)

- Seed por slot: `HMAC-SHA256(server_seed, "{client_seed}:{nonce}:slot:{n}")`,
  n = 1..5 (hex de 64 caracteres, vira `specimens.seed`).
- Bytes 0–3: tier via `RarityTable::rollTier` (slot 5 sem comum). O resto
  do roll dentro da faixa do tier escolhe a espécie entre as do tier, em
  ordem de dex (igual ao `pickTier`/`BY[tier]` do protótipo).
- Bytes 4–27: `ResolveSpecimen` (função pura, já existe e tem teste):
  shiny, tamanho, natureza e IVs.
  - **Diferença do protótipo:** o tamanho continua com a inversa da normal
    (já implementada e testada) no lugar do Box-Muller do protótipo. A
    distribuição é a mesma (μ .5, σ .17). Quem confere é a nossa página
    `/verificar` (fase 2), que usa o mesmo código.
- Bytes 30–31 (e 29): parâmetros do holo da carta (ângulo, offset,
  intensidade), derivados no servidor.
- Bytes 28–29 não definem o mint. **O mint é real**: o próximo número por
  espécie, com `lockForUpdate` nas linhas de `species` envolvidas. O
  índice único `(species_id, mint_number)` segura o resto.
- Nova classe `DrawPack` (pura): recebe server seed, client seed, nonce e
  a lista de espécies por tier, e devolve os 5 sorteios. Quem grava é o
  `OpenPack`.

## Schema

- `users`: `next_server_seed` (texto criptografado, hidden) e
  `next_server_seed_hash`. São gerados no primeiro acesso à tela do pack e
  a cada abertura.
- `openings`: `opened_slots` (tinyint, bitmask das 5 bolas).
- `specimens`: `slot` (1–5) e `revealed_at` (nullable).
- `species`: `sprite_animated_path` e `sprite_animated_shiny_path`
  (nullable). O `pokedex:sync` baixa os GIFs animados de
  `generation-v/black-white/animated` pro storage local. Se faltar algum,
  o fallback é o PNG que já existe.

## Actions e rotas (`auth`, `onboarded`)

| Método | Rota | Action |
|---|---|---|
| GET | `/pack` | `BuildPackScreen` (estado: idle com hash e countdown, pack em andamento com as bolas abertas, ou o resumo do último) |
| POST | `/pack/abrir` | `OpenPack` (cooldown, transação, mint lock, incrementa nonce, já faz o commit da próxima seed). 409 se estiver em cooldown |
| POST | `/pack/{opening}/bolas/{slot}` | `RevealSlot` (só o dono; idempotente; marca o bit e o `revealed_at`; na 5ª revela a server seed) |
| PATCH | `/pack/client-seed` | `UpdateClientSeed` (1–24 caracteres, sem espaço) |

O POST de abrir e o de revelar respondem JSON (`useHttp`), igual ao
fluxo de entrada: o cliente anima enquanto espera e só encena o que o
servidor devolveu.

Home: o tile "Pack de hoje" e o item Packs do dock abrem `/pack`. O tile
mostra "Disponível" ou a contagem até o próximo pack.

## Front

- `pages/pack/Show.vue`, tela escura (fundo `#1c1f26`), fora do
  `EntryLayout`.
- GSAP pras timelines (dependência nova, prevista no SPEC e no
  CLAUDE.md). Canvas pra poeira e partículas.
- Componentes: `PackBall` (bola central com carga), `MiniBall`,
  `SlotSprite` (escala inteira 1×/2×/3×, `pixelated`), `Ceremony`,
  `PackSummary` (cartas com tilt e holo), `SpecimenSheet` (ficha),
  `FairnessReceipt` (comprovante) e `useSfx` (o `class SFX` do
  protótipo, com as mesmas frequências e envelopes).
- Mudo no canto superior direito, guardado em localStorage.
- As três mudanças obrigatórias: clique por bola em qualquer ordem, com
  wobble de espera, hint "Faltam N", Espaço/Enter e "Abrir todas" depois
  de 1,5s; sprites maiores; e mobile em 4 + 1, com o slot 5 em destaque.
- `prefers-reduced-motion`: sem screen shake, menos partículas e cerimônia
  encurtada.

## Testes (Pest)

- `DrawPack`: determinismo (mesma entrada, mesma saída), slot 5 nunca
  comum, distribuição dos tiers numa amostra grande dentro da tolerância.
- `OpenPack`: cooldown, 5 espécimes, nonce incrementado, commit da próxima
  seed, mint sequencial por espécie, recusa quando está em cooldown.
- `RevealSlot`: só o dono, idempotente, a server seed só aparece depois da
  5ª, o espécime só entra na coleção, no ranking e no feed depois de
  revelado.
- A resposta da explosão não contém espécie, tier nem shiny.
- HTTP: guards, validação da client seed, o estado da tela depois de
  recarregar.
