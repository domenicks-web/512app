# SPEC

Jogo de abrir pack de Pokémon geração 1, com espécimes únicos e colecionáveis.
PWA privado, uso entre amigos, sem monetização.

Nome do projeto ainda não definido. Usar `pokedex-game` como slug provisório.

---

## 1. Visão

O jogador abre um pack por dia. Cada pack revela 5 espécimes.

Um espécime não é "um Pikachu". É **aquele** Pikachu: com shiny ou não, tamanho
próprio, IVs próprios, natureza própria e número de registro sequencial. Dois
jogadores podem ter Pikachu e ainda assim um valer muito mais que o outro.

A referência mental é skin de CS. O item tem identidade, tem variação medível, e
tem história de quem abriu e quando.

O motor emocional do jogo não é o Pokémon que sai. É o **momento de revelação** e a
possibilidade de mostrar pro grupo depois.

### O que segura o jogador voltando

1. Pack diário grátis, sem exceção
2. Coleção comparável: dá pra ver o espécime do amigo lado a lado com o seu
3. Trocas entre jogadores
4. Feed do grupo mostrando as aberturas boas de todo mundo

Sem os itens 2 a 4 o jogo cansa em uma semana. Eles não são "extras".

---

## 2. Stack

| Camada | Escolha |
|---|---|
| Backend | Laravel 12 (PHP 8.3+) |
| Frontend | Inertia + Vue 3 |
| CSS | Tailwind |
| Build | Vite + `vite-plugin-pwa` |
| Banco | PostgreSQL |
| Auth | Laravel Socialite, provider Google |
| Animação | GSAP |
| Realtime | Laravel Reverb (só a partir da fase 4) |

**Não usar Firebase.** Firestore é documento e o domínio aqui é relacional. Cloud
Functions exige plano Blaze com cartão. Socialite resolve o login sem trazer mais
um fornecedor.

### Deploy

VPS própria ou Oracle Cloud free tier. Evitar plataformas que hibernam container
no plano gratuito, porque o PWA fica com 30 segundos de cold start e a sensação de
lentidão mata o projeto.

---

## 3. Conceitos do domínio

| Termo | Significado |
|---|---|
| **Species** | Uma das 151. Dado estático, vem da PokéAPI. |
| **Specimen** | Uma instância única em posse de um jogador. É o item colecionável. |
| **Opening** | Um evento de abertura de pack. Guarda as seeds da auditoria. |
| **Roll** | Uma rolagem derivada da seed. Determinística. |

Regra de ouro do vocabulário: nunca chamar Specimen de "card" nem de "pokemon" no
código. Species e Specimen são coisas diferentes e confundir as duas vai gerar bug.

---

## 4. Schema

```sql
users
  id
  name
  email                    unique
  google_id                unique, nullable
  avatar_url               nullable
  client_seed              string, o jogador pode trocar
  nonce                    integer, default 0
  next_pack_at             timestamp, nullable
  created_at, updated_at

species                    -- 151 registros, populados por seeder
  id                       = dex_number, 1 a 151
  name                     "Bulbasaur"
  slug                     "bulbasaur"
  type_1                   enum
  type_2                   enum nullable
  rarity_tier              enum: comum | incomum | raro | lendario
  base_stat_total          integer
  evolution_stage          1 | 2 | 3
  base_height_m            decimal(4,2)
  base_weight_kg           decimal(6,2)
  sprite_path              string
  sprite_shiny_path        string
  artwork_path             string
  flavor_pt                text nullable
  created_at, updated_at

openings
  id
  user_id                  fk
  server_seed              string, nullable até o reveal
  server_seed_hash         string, sha256, gravado ANTES do sorteio
  client_seed              string, cópia do valor no momento
  nonce                    integer
  revealed_at              timestamp nullable
  created_at

specimens
  id
  opening_id               fk
  user_id                  fk, dono atual
  species_id               fk
  seed                     string, 64 chars hex, fonte de tudo
  is_shiny                 boolean
  size_roll                decimal(6,5), 0 a 1
  size_class               enum: xxs | xs | m | xl | xxl
  nature                   enum, 25 valores
  iv_hp, iv_atk, iv_def    smallint, 0 a 31
  iv_spa, iv_spd, iv_spe   smallint, 0 a 31
  iv_total                 smallint, gerado, 0 a 186
  mint_number              integer, sequencial POR species
  hash                     string, HMAC de verificação
  nickname                 string nullable, máx 16 chars
  caught_at                timestamp
  created_at, updated_at

  unique (species_id, mint_number)
  index  (user_id, species_id)

trades
  id
  from_user_id             fk
  to_user_id               fk
  status                   enum: pending | accepted | declined | cancelled
  message                  string nullable
  responded_at             timestamp nullable
  created_at, updated_at

trade_items
  id
  trade_id                 fk
  specimen_id              fk
  side                     enum: offered | requested
```

### Sobre mint_number

É sequencial por espécie no servidor inteiro, não por jogador. O primeiro
Charizard aberto na história do servidor é o `#0001` e nunca mais vai existir
outro. Isso cria valor sem precisar de nenhuma economia.

Gerar dentro de transação com lock, senão dois packs simultâneos colidem.

---

## 5. Tabela de raridade

### Classificação das 151

Regra base, aplicada no seeder:

- `lendario`: Articuno, Zapdos, Moltres, Mewtwo, Mew
- `raro`: `evolution_stage = 3`, ou `base_stat_total >= 490`
- `incomum`: `evolution_stage = 2`
- `comum`: o resto

Casos que precisam de override manual no seeder: Dratini e Larvitar-likes que são
stage 1 mas pertencem a linha forte, Snorlax, Lapras, Aerodactyl, Ditto, Eevee,
Chansey, Kangaskhan, Tauros, Porygon, Farfetch'd. Deixar uma constante
`RarityTable::OVERRIDES` para isso, mapeando `dex_number => tier`.

### Pesos por pull

| Tier | Peso |
|---|---|
| comum | 60.0% |
| incomum | 28.0% |
| raro | 10.5% |
| lendario | 1.5% |

Pack tem 5 slots. Slots 1 a 4 usam a tabela livre. **Slot 5 garante incomum ou
acima**, redistribuindo os pesos entre os três tiers superiores. Isso evita o pack
totalmente lixo, que é o que faz jogador largar.

### Camadas de sorte

| Camada | Distribuição | Papel |
|---|---|---|
| Shiny | 1 em 512 | o momento, sprite alternativo |
| Tamanho | normal, μ=0.5 σ=0.17 | extremos raros, xxs e xxl ~2% cada |
| IV | uniforme 0 a 31 por stat | "potencial", 186 é perfeito |
| Natureza | uniforme 1 em 25 | sabor, define cor de destaque na UI |
| Mint | sequencial | prestígio de ter chegado primeiro |

A taxa de shiny é intencionalmente generosa. Com 5 pulls por dia, um jogador tem
cerca de 25% de chance de shiny por mês. Num grupo de 6 pessoas isso dá mais ou
menos um shiny e meio por mês no grupo, que é a frequência certa: raro o bastante
pra virar assunto, comum o bastante pra não desistir.

Deixar as taxas em `config/game.php`, nunca hardcoded.

### Classes de tamanho

| size_roll | size_class |
|---|---|
| < 0.02 | xxs |
| 0.02 a 0.20 | xs |
| 0.20 a 0.80 | m |
| 0.80 a 0.98 | xl |
| >= 0.98 | xxl |

Altura exibida = `base_height_m * (0.7 + size_roll * 0.6)`.

Um Rattata shiny xxl com IV alto precisa valer mais no grupo do que um Mewtwo
comum. Se isso acontecer, o design tá funcionando.

---

## 6. Provably fair

Impede que o dono do servidor trapaceie, e mais importante, impede que os amigos
**achem** que ele trapaceia.

### Fluxo

1. Servidor gera `server_seed` aleatório e guarda `server_seed_hash = sha256(server_seed)`
2. O hash é mostrado pro jogador **antes** de abrir
3. Jogador pode definir seu `client_seed` a qualquer momento
4. Na abertura, deriva tudo de `HMAC_SHA256(server_seed, "{client_seed}:{nonce}")`
5. Depois da revelação, o `server_seed` é publicado e o `nonce` incrementa
6. Existe uma página `/verificar` onde o jogador cola as três coisas e refaz a conta

### Derivação

O HMAC dá 64 chars hex. Consumir em fatias, sempre na mesma ordem:

```
bytes  0-3    slot 1 species roll
bytes  4-7    slot 1 shiny roll
bytes  8-11   slot 1 size roll
bytes 12-15   slot 1 nature roll
bytes 16-27   slot 1 IVs (2 bytes cada)
...e assim por diante, ou re-HMAC por slot com sufixo ":slot:{n}"
```

Preferir o re-HMAC por slot, fica mais legível e não acaba entropia.

### Hash de verificação do espécime

```php
$hash = hash_hmac(
    'sha256',
    "{$speciesId}|{$seed}|{$mintNumber}|{$caughtAt}",
    config('game.specimen_secret')
);
```

Aparece truncado na ficha do espécime, tipo `a3f9c2...`. Serve de identidade
visual e prova de autenticidade. Não é blockchain e não precisa ser.

---

## 7. Regras inegociáveis

1. **Todo sorteio acontece no servidor.** O front recebe o resultado já decidido e
   só encena. Se qualquer rolagem passar perto do cliente, os amigos farmam shiny
   no DevTools em dois dias e o jogo acaba.
2. **`ResolveSpecimen` é função pura.** Entra seed, sai espécime. Nunca chama
   `rand()`, `now()` nem toca no banco por dentro. Isso é o que torna o
   provably fair auditável e o teste possível.
3. **Sprites são servidos do storage próprio.** Clonar `PokeAPI/sprites` uma vez,
   converter, guardar. Nunca apontar pro GitHub em runtime.
4. **Nada de dinheiro real.** Sem compra, sem loja, sem cripto. Isso é o que
   mantém o projeto fora do radar da Nintendo.
5. **Não publicar em loja de aplicativo.** Distribuição é link no grupo.

---

## 8. Fases

### Fase 1, o núcleo
- [ ] Projeto Laravel + Inertia + Tailwind + PWA
- [ ] Login Google via Socialite
- [ ] Command `php artisan pokedex:sync` que importa as 151 e baixa sprites
- [ ] `ResolveSpecimen` pura, com teste de determinismo
- [ ] `OpenPack` com cooldown de 24h
- [ ] Tela de abertura com animação
- [ ] Tela de coleção com grid

**Critério de pronto:** abrir um pack, ver os 5 bichos aparecerem, e a coleção
encher. Se isso não der vontade de abrir de novo amanhã, parar e repensar antes de
construir o resto.

### Fase 2, a prova
- [ ] Commit e reveal de seeds
- [ ] Página `/verificar`
- [ ] Ficha detalhada do espécime com todos os atributos e o hash

### Fase 3, o social
- [ ] Perfil público de cada jogador
- [ ] Comparar dois espécimes da mesma espécie lado a lado
- [ ] Ranking: mais espécies, mais shinies, maior IV, menor mint

### Fase 4, as trocas
- [ ] Propor, aceitar, recusar
- [ ] Notificação via Reverb
- [ ] Histórico de donos anteriores de cada espécime

### Fase 5, o polimento
- [ ] Tilt com giroscópio no celular
- [ ] Som de abertura
- [ ] Apelido no espécime
- [ ] Feed do grupo

---

## 9. Direção de arte

O padrão de qualidade é: parecer app publicado, não projeto de faculdade.

- Fundo escuro, quase preto, com vinheta. O sprite é a única coisa iluminada.
- Sprite animado renderizado com `image-rendering: pixelated`, escalado em
  múltiplo inteiro. Nunca deixar o navegador interpolar, borra e fica amador.
- Shiny tem tratamento próprio: partículas, flash branco de um frame, e um brilho
  que percorre o sprite. O jogador precisa **saber** antes de ler o texto.
- A `seed` alimenta os parâmetros do brilho holográfico: ângulo da gradiente,
  offset, intensidade. Dois Pikachus brilham diferente. É isso que amarra
  "é único" com "é bonito".

### Timeline da abertura

O ritmo importa mais que o efeito:

1. A bola treme, acelerando
2. Estoura com flash branco
3. **Pausa de meio segundo em preto.** Esse silêncio é o que cria a tensão.
4. Comuns revelam rápido, em sequência, sem cerimônia
5. Quando vem raro ou shiny: o tempo trava, o fundo escurece mais, o sprite sobe
   sozinho, e só depois o nome aparece

Suspense é 80% do prazer de abrir pack. Efeito sem timing não funciona.

---

## 10. Fora de escopo por enquanto

Não implementar, nem sugerir, até as 5 fases fecharem:

- Batalha
- Evolução
- Economia com moeda
- Gerações 2 em diante
- Qualquer coisa com dinheiro real
