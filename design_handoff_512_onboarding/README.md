# Handoff: 512 — Loading, Login, Cadastro e Home

## Visão geral
Fluxo de entrada do **512**, um PWA privado de abrir packs de Pokémon da Geração 1, com espécimes únicos e colecionáveis. As regras de produto estão em `SPEC.md`. O fluxo é uma sequência contínua: **loading → login → cadastro (3 passos) → home**. Cada tela nasce da anterior por animação; nenhuma tela aparece com corte seco.

## Sobre os arquivos de design
Os arquivos deste pacote são **referências de design feitas em HTML**: protótipos que mostram o visual e o comportamento esperados. **Não são código de produção para copiar.** A tarefa é **recriar esse design no ambiente do projeto** (React/Next, Svelte etc.), usando os padrões e bibliotecas que o projeto já tem. Se ainda não houver ambiente, escolha o framework mais adequado para um PWA.

Para ver o protótipo, abra `512 Experiência.dc.html` no navegador, com o `support.js` na mesma pasta. O canto inferior esquerdo tem o botão "Rever animação". No código, a prop `startAt` (`loading` | `login` | `onboarding` | `home`) pula direto para uma etapa, `speed` (de 0.5 a 2) muda a velocidade e `dotField` liga ou desliga o campo de pontos.

## Fidelidade
**Alta fidelidade.** Cores, tipografia, espaçamentos, raios, sombras e timings são finais. Recrie pixel a pixel.

**Única troca planejada:** no protótipo, os parceiros e avatares são **criaturas placeholder** (blobs coloridos com olhos). No app, use os **ícones de Pokémon que o projeto já tem** (ver "Parceiro / avatar" abaixo).

---

## Design tokens

### Cores
| Token | Hex | Uso |
|---|---|---|
| red | `#f24d4d` | primária: botões, fundo do logo, anel de foco, barra de progresso |
| red-cap | `#ff5757` | metade de cima da pokébola (somente nela) |
| red-ink | `#c0342f` | texto de erro e texto vermelho sobre fundo claro |
| ink | `#2b2f36` | texto principal, ícones, card escuro (Ranking) |
| ink-deep | `#1c1f26` | card do Feed |
| steel | `#434a53` | faixa e botão da pokébola |
| steel-light | `#e4e7eb` | centro do botão da pokébola |
| bg | `#eff0f2` | fundo do app |
| field | `#f4f5f6` | fundo dos inputs e do seletor de data |
| line | `#eceef0` / `#e4e6ea` / `#dfe1e5` | contornos, botão secundário, trilho das pills |
| muted | `#5a6069` (labels) · `#767c85` (apoio) · `#9aa0a8` / `#a3a8b0` (terciário) · `#b4b9c0` (placeholder) |
| online | `#3f8f6b` | ponto "amigos online" |
| shiny | `#4dd9e8` | selo e glow Shiny (só no Feed) |
| red-disabled | `#f5a3a3` | botão primário desabilitado |

Erro, pill: fundo `rgba(242,77,77,0.12)` com texto `#c0342f`. Anel de foco do input: `0 0 0 6px rgba(242,77,77,0.12)` + borda 2px `#f24d4d`.

### Tipografia
- **Inter** em toda a UI (`font-optical-sizing: auto`). No app, use os arquivos `Inter_18pt` citados no SPEC. Pesos: **900** em títulos e números, **800** em labels e botões, **600** em textos e inputs.
- Títulos: 27px / 900 / line-height 1.1–1.15 / letter-spacing −0.025em / `text-wrap: balance`.
- Números grandes da home: 64–72px / 900 / lh 0.9 / ls −0.03em.
- Labels: 13.5px / 800 / `#5a6069`. Texto de apoio: 14–14.5px / 600 / `#767c85`. Pills e tags: 12–12.5px / 800–900.
- O logo **não usa fonte**. Os "512" são contornos vetoriais do `logo.svg`.

### Raios
Cards de formulário 28px · tiles da home 22px · seletor de data 22px (linha selecionada 14px) · inputs, botões, pills e chips 999px · campo do logo 20px (≈35/600 do SVG).

### Sombras
- Card: `0 2px 4px rgba(43,47,54,0.05), 0 22px 56px rgba(43,47,54,0.18)`
- Botão primário (glow): `0 10px 24px rgba(242,77,77,0.42)`; no hover, `0 14px 30px …0.42` + `translateY(-2px)`; no active, `scale(.97)`
- Logo: `0 18px 40px rgba(242,77,77,0.32)`
- Tile claro: `0 2px 4px rgba(28,32,38,0.06), 0 14px 30px rgba(28,32,38,0.08)`

### Molas (springs)
Todas as entradas usam mola física, gerada como `linear()` a partir de rigidez *k* e amortecimento *c*. A função `spr(k, c)` está no protótipo. Fallback: `cubic-bezier(.34,1.5,.64,1)`. Valores usados: (150,19) deslizar, (170,19) card, (220,13) dígitos, (300,13) pop, (320,13) quique, (380,16) fechar cápsula. Em React, Framer Motion `type: "spring"` com stiffness ≈ k e damping ≈ c chega perto.

---

## Telas

### 1. Loading (≈3.6s)
O campo de pontos é um canvas fullscreen com **512 pontos** num grid de 32×16, espaçados 13px e centralizados.
1. **0–1.4s:** os pontos acendem em ordem aleatória (curva `rank^0.8`). O contador `000 / 512` fica 136px abaixo do centro (Inter 900, 15px, ls 0.1em, `#9aa0a8`) e fica vermelho ao chegar a 512. Um ponto especial (índice 245) é vermelho e maior.
2. **1.52s:** todos os pontos espiralam para o centro (swirl). O vermelho cresce até r=7.
3. **1.98s:** o ponto vermelho some e solta uma onda (ripple). A cápsula nasce com `scale(0) rotate(-30deg) → none`.
4. **2.24s:** a cápsula **abre como uma boca**: a parte de cima vai a `translateY(-20px) rotate(-18deg)`, a de baixo a `translateY(12px) rotate(9deg)`, ambas com pivô no lado esquerdo.
5. **2.3s:** o campo vermelho se expande a partir da cápsula (clip-path inset → full) enquanto o conjunto desliza do centro para a posição final. Os dígitos 5, 1 e 2 saem de dentro da cápsula, um a cada 80ms (`translateX(até a cápsula) scale(.15) rotate(-35deg) → none`).
6. **2.68s:** a cápsula fecha com mola (380,16). Em 2.7s o logo faz um squash (`1.07,.9 → .97,1.04 → 1`).
7. **3.15s:** a cápsula **pisca**: o centro do botão faz `scaleY(.08)` em 260ms.
8. **3.6s:** o logo **voa** até o card de login (translate + scale em 780ms, `cubic-bezier(.6,0,.25,1)`, em arco).

**Construção do logo:** partes separadas para poder animar, todas tiradas do `logo.svg` (viewBox 600×300, escala k=96/167.93):
- campo 337.5×154px, r≈20;
- cápsula de 96px em (18.6, 32);
- topo vermelho com o path original;
- base branca em meio círculo;
- faixa `#434a53` de 14.4px de altura;
- botão Ø32.3 com centro Ø17;
- dígitos com os paths originais, que começam em x≈143px.

**Ordem de pintura:** base branca → topo vermelho → faixa e botão por cima.

### 2. Login
O card branco tem max-width 400px, padding 34/30/30 e gap 22. O logo fica no topo em 50% do tamanho.
- **Entrada:** o card se abre a partir do retângulo do logo (clip-path, mola 170,19). Os filhos entram um a cada 70ms (`translateY(16px) → 0`).
- **Copy:** "Bem-vindo de volta" / "Entre com sua conta pra continuar" / labels "Email" e "Senha" / botão "Entrar".
- **Campo de pontos:** fica em modo ambiente, numa grade de 32×16 pela tela inteira (r 1.5, alpha .2). Num raio de 150px, os pontos se afastam do cursor e crescem.
- **Cada tecla** (email, senha, nickname) dispara uma onda vermelha no campo de pontos, saindo do caret.
- **Senha:** o input real tem texto transparente. Por cima, cada caractere aparece como uma **mini-pokébola de 14px**, que cai ao ser digitada (`translateY(-16px) scale(.3) rotate(-50deg) → none`, mola 320,13). O caret próprio é uma barra vermelha de 2×20px piscando.
  - **Mostrar senha:** botão de olho de 40px dentro do campo, à direita.
    - Mostrar: o olho pisca (scaleY .1) e cada pokébola, em cascata de 38ms, gira 200° e encolhe; o caractere real sai dela com um pulinho (`translateY(8px) scale(.2) → translateY(-3px) scale(1.25) → 1`).
    - Esconder: o caminho inverso.
    - Estados: escondida = olho cinza `#8a9099` com traço diagonal; visível = olho vermelho, sem traço.
  - **Seleção:** esconda a seleção nativa (`::selection` transparente) e destaque os slots selecionados com `rgba(242,77,77,0.22)`.
- **Validação:**
  - Email precisa passar em `/.+@.+\..+/`. Se falhar, a mensagem é "Esse email não parece certo".
  - Senha não pode ser vazia. Se estiver, a mensagem é "Falta a senha".
  - Com erro, o card treme, a pill de erro entra com pop, a borda do campo fica vermelha e o foco volta para o campo.
- **Envio:**
  1. O botão encolhe até virar um círculo de 54px e o texto some.
  2. Nasce uma pokébola dentro dele, que **balança 3 vezes** (−24°, +22°, −16°, como na captura) em 1.5s.
  3. A pokébola infla (1.7) e some, com glow e uma onda grande.
  4. O card amassa e sobe (`translateY(-110px) scale(.92,.06)`) e a tela vai para o cadastro.
- **Botão primário:** altura 58, Inter 800 / 17px, ícone de pokébola de 22px à esquerda com gap de 10. O ícone é um círculo branco cheio com a faixa e o anel vazados por máscara. O SVG está no protótipo (`mask id="pb512"`).

### 3. Cadastro (3 passos)
Coluna centralizada com gap 18. De cima pra baixo:
- **Chip de perfil:** 400×54, vidro (`rgba(255,255,255,.82)` + blur 10). Avatar de 38px + "Nickname" + **#tag**.
- **Pills de progresso:** 3 de 40×7. A atual fica 34% preenchida e as concluídas 100%.
- **Card:** max-width 440.

**Transição entre passos:** o card **se espreme até virar a pill** do passo atual (480ms, `cubic-bezier(.5,0,.75,.35)`), a pill "engole" (`scale(1.3,2.1)`) e o card do próximo passo **nasce da pill seguinte** (mola 190,16), com o conteúdo entrando um a cada 70ms.

- **Passo 1, Nickname:** "Ei, treinador! Como posso te chamar?" / máximo de 16 caracteres / botão "Continuar", desabilitado (`#f5a3a3`) enquanto estiver vazio. Se tentar continuar vazio, o input treme.
  - A cada tecla, a **tag #6401 gira como caça-níquel**: 4 colunas de 0 a 9 com `translateY(-d×1.25em)`, transição de 0.55s mais 0.13s por coluna, mola.
  - A tag real vem do backend (SPEC). A animação só acontece ao recebê-la ou mudá-la.
- **Passo 2, Data de nascimento:** "Qual é a sua data de nascimento, treinador?" / "Só pra garantir que você já pode aparecer no Pokédex."
  - **Três rodas** (dia, mês, ano) em grid 1 / 1.3 / 1.5, altura 138, linhas de 46px, com a linha do meio branca.
  - Item ativo em `#2b2f36` e scale 1. Os outros em `#b9bdc4` e scale .78.
  - Funciona com scroll (throttle de 70ms) ou toque. Rodapé: "Role ou toque nos números".
  - Botões "Voltar" (secundário) e "Continuar".
- **Passo 3, Parceiro:** "Escolha seu parceiro!" / "Ele vai te representar pra galera, então escolhe com o coração."
  - Grid de 5 colunas com tiles de 64px e gap 14.
  - **Selecionado:** anel girando em `conic-gradient(#f24d4d, #ffffff, #f24d4d)`, 3.5px, 2.2s por volta, com scale 1.12.
  - **Os outros reagem:** pulam em cascata pela distância até o escolhido (−9px, 55ms por unidade de distância) e "olham" para ele, inclinando até ±7°.
  - O escolhido **voa em arco até o avatar** do chip (620ms) e o avatar dá um pop.
  - Se tentar entrar sem escolher, todos pulam e a grade treme.
  - Botões "Voltar" e "Entrar no jogo". Ao concluir, o chip voa para o canto superior esquerdo (onde fica o avatar na home) e os pontos convergem para lá.

### 4. Home
- **Topo esquerdo:** avatar de 48px com anel `0 0 0 3px #fff, 0 0 0 5px #f24d4d`, nickname (15.5/900) e "48 de 151 espécies" (12/600). **Não tem** barra de status (hora, wifi, bateria) **nem** dicas de controle (A/B).
- **Trilho de tiles:** quadrados com largura `clamp(200px, 23vw, 300px)`, gap 22, padding-left `clamp(24px, 7vw, 96px)`. Entram deslizando da direita, um a cada 70ms (`translateX(180px) rotate(5deg) scale(.9)`).
  1. **Pack de hoje:** fundo `#f24d4d`, data "13 out" no canto superior direito, pokébola grande de 104px flutuando (−7px, −3°, loop de 3.2s) e pill "1 em 512". Ao abrir, a tampa levanta.
  2. **Coleção:** branco, "48" (64px) + "/151", barra de progresso de 8px com 32% preenchido.
  3. **Ranking:** `#2b2f36`, "2º" (72px, branco) + "em espécies no grupo".
  4. **Trocas:** branco, "3" + "propostas de troca".
  5. **Feed do grupo:** `#1c1f26` com glow radial ciano, selo "SHINY", "Ramon abriu um Rattata", "há 12 min · #0004".
- **Seleção:** um **anel colorido girando** (4px, conic de vermelho e branco, 3s por volta) desliza até o tile ativo (550ms, mola). Em cima, um rótulo com quadradinho vermelho mostra o nome do tile. O trilho rola para manter o tile ativo visível.
  - Controle: setas ← →, hover ou clique. Enter, ou clique no tile já ativo, aperta o tile (`scale .94 → 1.03 → 1`).
  - O olhar do avatar acompanha o tile ativo.
- **Dock inferior:** vidro com botões de 56px. O primeiro é a cápsula (`favicon.svg`) em fundo vermelho; depois Coleção, Packs, Ranking, Trocas, Amigos e Ajustes.
  - No hover, cada ícone tem micro-animação: cartas abrem em leque, cápsula abre, barras sobem, setas trocam, mão acena, sliders correm. O tooltip escuro fica 68px acima.
- **Rodapé:** ponto verde pulsando + "2 amigos online".

---

## Parceiro / avatar: Pokémon no lugar das criaturas
Use os **ícones de Pokémon que já existem no projeto**. O jogo é sobre Pokémon da Gen 1 e o avatar deve ser coerente com a coleção. Adaptações:
- **Grade do passo 3:** mostre os iniciais/opções definidos pelo SPEC (por exemplo Bulbasaur, Charmander, Squirtle, Pikachu…), com o sprite centralizado no tile branco de 64px.
- **"Olhar" e piscar:** sprites não têm olhos animáveis. No lugar disso:
  - os não escolhidos fazem `scaleX(-1)` para "virar" na direção do cursor ou do escolhido, mais a inclinação de ±7° que já existe;
  - um sprite aleatório faz um micro-pulo a cada ~900ms em vez de piscar;
  - o pulo em cascata e o voo até o avatar continuam iguais.
- **Avatar da home:** o mesmo sprite. No lugar do olhar, o sprite vira (`scaleX`) conforme o tile ativo fica à esquerda ou à direita.
- Sprites em pixel art pedem `image-rendering: pixelated` e tamanhos em múltiplos inteiros.

## Estado
- `screen`: `loading | login | onboarding | home`
- Login: `email`, `pw`, `showPw`, `pwFocus`, seleção (`selA`, `selB`), `err` (`email | pw | null`), `busy`
- Cadastro: `step` (0–2), `nick` (máx. 16), `tag` (4 dígitos, vindo do backend), `day`/`month`/`year`, `pick` (índice do parceiro), `partner`
- Home: `tile` (0–4), `dock` (hover)
- Guarde um contador de geração para cancelar timers e animações quando a tela mudar ou o fluxo for reiniciado.

## Comportamento e acessibilidade
- Respeite `prefers-reduced-motion`: corte as molas para fades curtos e desligue o campo de pontos e o loop da pokébola.
- O campo de pontos é decorativo (`pointer-events: none`, `aria-hidden`). Limite o DPR a 2.
- O botão do olho precisa de `aria-label` alternando entre "Mostrar senha" e "Esconder senha" e de `preventDefault` no mousedown, para não tirar o foco do input.
- O cadastro é só por convite (SPEC). Falta definir onde entra o código de convite; a sugestão é um passo 0 antes do nickname, com a mesma mecânica de pill.

## Assets
- `assets/logo.svg`: logo completo (campo vermelho + cápsula + "512" em contornos). Base do loading e do login.
- `assets/favicon.svg`: cápsula sozinha (dock, ícone do app, favicon).
- O ícone de pokébola dos botões, a mini-pokébola da senha e os ícones do dock são SVGs inline no protótipo. Copie de lá.
- Os blobs de parceiro são placeholders. Troque pelos sprites de Pokémon do projeto.

## Arquivos
- `512 Experiência.dc.html`: protótipo completo. A lógica de animação, com todos os timings, está na classe `Component`: `runLoading`, `flight`, `enterLogin`, `flipPw`, `loginGo`, `collapseToPill`/`morphFromPill`, `pick`, `finishOnboarding`, `enterHome`, `placeRing`. O campo de pontos está em `tick`.
- `support.js`: runtime necessário só para abrir o protótipo. Não vai para produção.
- `SPEC.md`: regras do produto.
- `ADMIN.md` + `512 Admin.dc.html`: painel admin (convites e jogadores), com schema extra, comandos Artisan, rotas e telas.
