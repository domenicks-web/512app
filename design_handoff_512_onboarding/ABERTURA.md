# Handoff: 512 — Abertura do pack

## Visão geral
O jogador abre o pack diário: segura a pokébola até carregar, ela explode e caem 5 mini-bolas. Comuns e incomuns abrem rápido. Raros, lendários e shinies têm cerimônia própria. No fim aparece o resumo com as cartas, o comprovante de sorteio justo e a ficha de cada espécime. As regras de sorteio estão no `SPEC.md`.

## Sobre o arquivo de design
`512 Abertura.dc.html` é **referência de design em HTML**, não código de produção. Recrie em Laravel 12 + Inertia + Vue 3 + Tailwind, com GSAP para timelines e Web Audio para som. Abra o arquivo no navegador com `support.js` na mesma pasta.

**Fidelidade alta.** Cores, timings, curvas, copy e sons são finais, **exceto as 3 mudanças obrigatórias abaixo**.

---

## ⚠ Mudanças obrigatórias (diferem do protótipo)

### 1. Cada bola abre com clique do jogador, na ordem que ele quiser
No protótipo as 5 bolas abrem sozinhas, em sequência. **Em produção isso não acontece.**

- Depois que as 5 bolas pousam, ficam paradas com um balanço leve de espera (wobble de ±4° a cada ~2s, com fase diferente por bola). Nenhuma abre sozinha.
- O jogador toca/clica em **qualquer bola, em qualquer ordem**. Só aquela abre.
- Bola comum/incomum: roda o `pop` (≈0.5s) na hora. Durante o pop as outras bolas continuam clicáveis, então dá pra abrir várias em sequência rápida.
- Bola de cerimônia (raro, lendário, shiny): roda a cerimônia completa. Enquanto ela roda, as outras bolas ficam bloqueadas. Depois do "Toque para continuar", o sprite volta para o slot e as bolas restantes ficam clicáveis de novo.
- **Não dá dica da raridade antes do clique.** Todas as bolas fechadas são iguais. O brilho colorido só aparece depois do clique, no começo da cerimônia (o tremor de ~650ms com drop-shadow da cor).
- Hint abaixo das bolas: "Toque nas bolas para abrir" e, depois da primeira, "Faltam N". Some quando N = 0.
- Atalho: Espaço/Enter abre a próxima bola fechada da esquerda pra direita (acessibilidade). Cada bola é um `<button>` com `aria-label="Abrir bola N"`.
- Botão secundário discreto "Abrir todas" (pílula 36px, `rgba(255,255,255,.08)`, texto `#c3cad4`), aparece depois de 1.5s. Ele abre as fechadas em sequência, no modo rápido do protótipo (`fast`). **Cerimônias continuam pedindo toque para continuar.**
- O resumo só aparece depois que as 5 estiverem abertas: 700ms de espera e entra a transição circular.
- **Recarregar a página no meio:** o pack já foi sorteado e gravado no servidor no momento da explosão. Ao voltar, mostre as bolas abertas como sprites e as fechadas ainda clicáveis (`opened_slots` no estado do pack ou em localStorage). Nunca sortear de novo.

### 2. Pokémon maiores nos slots
No protótipo o sprite do slot tem altura máxima 106px e escala inteira até 2×. Isso fica pequeno.

- Desktop (≥ 768px): container do slot com **altura 168px**, sprite com escala inteira **até 3×** (máx. 160px de altura). Grid com `width: min(880px, 94vw)`.
- Mini-bola: 56px no desktop e 52px no mobile (antes era 46).
- Mantenha `image-rendering: pixelated` e sempre escala **inteira** (1×, 2×, 3×), nunca fracionária. Calcule `k = floor(min(cap, maxW/naturalW, maxH/naturalH))`, com mínimo 1.
- O nome embaixo sobe para 14px / 800.

### 3. Layout mobile: 4 em cima, 1 embaixo no centro
Abaixo de 768px, 5 colunas deixam tudo minúsculo. Use **2 linhas: slots 1–4 em cima e slot 5 sozinho, centralizado embaixo.**

- Grid `grid-template-columns: repeat(4, minmax(0,1fr))`, gap 10px horizontal e 18px vertical. O slot 5 fica em `grid-column: 2 / span 2` (centralizado) e é um pouco maior: container 150px contra 120px dos outros.
- **O slot 5 é o garantido "incomum ou melhor"** (como diz o idle: "O último é incomum ou melhor"), então fica em destaque. Mantenha essa leitura.
- Bloco das bolas centralizado verticalmente, levemente abaixo do centro (`top: 54%`), em vez de colado no fundo (`bottom: 10vh`) como no protótipo.
- Sprites dos 4 de cima: escala inteira até 2× (máx. ~112px). Slot 5: até 3× (máx. ~140px).
- Hit target de cada bola fechada: no mínimo 64×64px, mesmo que a bola visual seja 52px.
- Ao voltar da cerimônia, o sprite voa até o slot real no layout atual. Meça o `getBoundingClientRect()` do destino na hora, não use posição fixa.

---

## Fluxo (como está no protótipo, fora as mudanças acima)

### Idle
- Fundo `#1c1f26` com glow `radial-gradient(70% 45% at 50% 46%, rgba(242,77,77,.16), transparent)` e 56 partículas de poeira subindo (canvas).
- Topo: "PACK DE HOJE · {dd mmm}" (11/800/ls .2em `#7d8794`), "Cinco espécimes" (900, `clamp(30px,5vw,44px)`), "O último é incomum ou melhor" (14/600 `#7d8794`).
- Pokébola central `min(210px, 50vw, 30vh)`, flutuando (±7px, 1.8 rad/s) e inclinando até 7° na direção do cursor.
- Anel de carga SVG em volta (r=48, traço `#f24d4d` 1.8).
- Rodapé: hint "Segure a bola para abrir", chip "Hash do servidor {6…4}" e chip editável de **client seed** (máx. 24 caracteres, sem espaço, Mono). Editar a client seed muda o sorteio.

### Carga
- Segurar (pointer, Espaço ou Enter) enche o anel em **1.35s**. Soltar esvazia em 0.45s.
- Durante a carga: tremor que acelera (frequência 3 → 30 Hz, amplitude até 13°), botão central acende vermelho, glow de carga, partículas puxadas para a bola, rumble grave subindo (40 → 108 Hz) e ticks a cada meia volta.
- Prop `abertura = "Tocar"`: um toque enche sozinho em 1.7s.

### Explosão
Flash branco, 150 partículas, metades da bola voando, screen shake de 380ms, `vibrate([30,20,60])`. Escuro por ~800ms. **O POST que sorteia o pack acontece aqui** (ou antes da carga, com hash já mostrado). As 5 mini-bolas caem com 95ms de intervalo e squash ao pousar.

### Pop (comum/incomum)
Tremor de 220ms, tampa gira −125°, sprite sobe de `scale(.2)` com overshoot 1.12 em 480ms, nome + barra de cor do tier.

### Cerimônia (raro / lendário / shiny)
1. Bola treme com glow da cor (raro `#E07C55`, lendário `#FFC94D`, shiny `#4DD9E8`). 650ms.
2. Congela: as outras bolas descem e somem (opacity .06), escurece (.58, ou .72 em lendário/shiny), o glow do fundo troca para a cor.
3. A bola voa para o centro (132px) em 850ms.
4. 3 wobbles com "toc" e pausas crescentes (raro 420/560/820ms, lendário 520/680/980ms). Lendário tem raios girando atrás e tremor de tela. Shiny solta estrelas ciano.
5. Abre: halo, feixe de luz, 60 partículas. Shiny tem flash branco e 34 estrelas.
6. Silhueta preta com drop-shadow da cor sobe em 1.6s e a cor entra em 460ms. Acorde (lendário com subgrave). Shiny ganha brilho varrendo pela máscara do sprite, 2×.
7. Nome letra a letra (48ms por letra, mola), chips (tier, Shiny, Nº mint; mint ≤ 10 em dourado), meta ("Primeiro X do servidor" se mint 1, tamanho, IV/186, natureza).
8. "TOQUE PARA CONTINUAR" pulsando. **Espera o toque.**
9. O sprite volta para o slot real em 680ms e o resto da cena volta ao normal.

### Resumo
Transição circular de dentro pra fora, saindo de `50% 85%`, em 1050ms. Título com o melhor do pack ("{Nome} shiny no pack de hoje"), subtítulo com contagem por tier. Cartas entram com `rotateX(-28deg)` em cascata de 90ms. Tilt 3D ao passar o mouse, com holo cuja posição segue o cursor. Ângulo, offset e intensidade do holo vêm da seed do espécime. Rodapé: contagem até o próximo pack, "Comprovante", "Ver coleção". O botão "Abrir outro (demo)" é **só do protótipo, remover**.

### Ficha (clique na carta)
Modal escuro com sprite até 4×, tamanho numa régua (XXS–XXL), 6 IVs com barras (31 em dourado), natureza, data de captura e hash do espécime com "Verificado".

### Comprovante
Hash antes, server seed revelada, client seed, nonce, `sha256(server_seed) = hash` e uma linha por slot com o HMAC e o resultado.

---

## Sorteio (servidor, nunca no cliente)
O protótipo sorteia no browser só para a demo. Em produção:
- Por slot: `HMAC-SHA256(server_seed, "{client_seed}:{nonce}:slot:{n}")`, n = 1..5.
- Bytes 0–3: tier (pesos c .6 / i .28 / r .105 / l .015; slot 5 sem comum) e índice da espécie no tier. 4–7: shiny (< 1/512). 8–11: tamanho (Box-Muller, σ .17). 12–15: natureza (mod 25). 16–27: 6 IVs (mod 32). 28–29: mint. 30–31: holo.
- Mint real = contador por espécie no banco, não o derivado.
- O `server_seed` só é revelado depois que o pack é aberto. O hash é mostrado antes.
- **Os modos `roteiro` ("Raro + shiny", "Lendário") são só demo. Remover.**

## Som
Tudo é sintetizado com Web Audio (`class SFX` no protótipo): rumble, tick, burst, land, pop, whoosh, freeze, tok, open, rise, chord, glint, summary. Copie as frequências e envelopes. O botão de mudo fica no canto superior direito e o estado vai para localStorage. Respeite `prefers-reduced-motion`: sem screen shake, partículas reduzidas e cerimônia encurtada.

## Assets
Sprites animados do PokeAPI (`generation-v/black-white/animated`, shiny em `/shiny/`), com PNG estático de fallback. **Em produção, hospedar localmente**, não usar raw.githubusercontent.

## Arquivos
- `512 Abertura.dc.html`: protótipo. Funções-chave: `idleFrame` (carga), `burst`, `pop`, `ceremony`, `toSummary`, `derive` (sorteio), `SFX`.
