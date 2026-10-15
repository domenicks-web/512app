# Fase 5: apelido no espécime e giroscópio

Decidido com o usuário em 15/10/2026, à tarde. O som de abertura e o feed
já existem. O PWA fica pro fim, porque depende do deploy (`https`).

## 1. Apelido no espécime

### Regra

- Cada dono dá **um** apelido ao espécime. Antes de salvar aparece a
  confirmação: "Pikachu vai se chamar **João**. Não dá pra mudar depois."
  Depois de confirmado, o apelido fica travado enquanto o dono for o mesmo.
- O apelido só existe enquanto o espécime está com aquele dono. Na troca
  (`AcceptTrade`) e na libertação de banido (`BanPlayer::release`), o
  apelido atual vai pro histórico (`specimen_transfers.nickname`) e o
  espécime fica sem apelido. Assim o novo dono ganha o direito de dar o
  dele. O "Professor Carvalho" nunca dá apelido.
- Apelido "travado" = `specimens.nickname` não nulo. Não existe remover.
- **Validação**: de 1 a 16 caracteres (`mb_strlen`), sem espaços nas pontas
  (o valor é aparado antes de validar), e não pode ficar vazio depois de
  aparado. Os limites ficam em `config/game.php`, em
  `specimen.nickname_min_length` (1) e `specimen.nickname_max_length` (16).
  A coluna já existe com 16.
- **Fora de escopo** (precisa atualizar o `SPEC.md` antes): vender
  "renomear de novo" na loja. A regra acima não atrapalha: uma compra
  futura só precisaria liberar a trava.

### Banco

- Migration nova: `specimen_transfers.nickname` string(16) nullable, com o
  apelido que o dono anterior tinha dado (`from_user_id`). Com `down()`.
- `specimens.nickname` já existe (`2026_09_13_071637`). Não muda.

### Backend

- `App\Actions\NameSpecimen::handle(Specimen $specimen, User $owner, string $nickname): Specimen`
  - Trava a linha com `lockForUpdate` dentro de transação, porque dois
    cliques rápidos não podem gravar dois apelidos.
  - Lança `SpecimenNamingNotAllowedException` se `$owner` não for o dono
    ou se o espécime já tiver apelido.
- Rota `POST /especime/{specimen}/apelido` → `NameSpecimenController`
  (fino) com `NameSpecimenRequest` (aparar, obrigatório, min/max do
  config). Responde redirect back (Inertia), com erro de validação em
  `nickname` quando a Action recusar.
- `AcceptTrade`: no `SpecimenTransfer::create`, grava
  `'nickname' => $specimen->nickname`. No `forceFill`, junto do
  `user_id`, zera o `nickname`.
- `BanPlayer::release`: lê `id` e `nickname` dos espécimes, grava o
  apelido em cada transfer e zera o apelido no `update` em massa.
- `PresentSpecimenSheet`:
  - Inclui `nickname` (atual) e `canName` (o espectador é o dono e o
    espécime está sem apelido).
  - Em cada entrada do `history`, inclui `specimenNickname`: o apelido
    que aquele dono deu. Na captura, é o `nickname` do primeiro transfer
    ou, se não houve troca, o apelido atual. Na entrada do transfer *i*,
    é o `nickname` do transfer *i+1* ou, no último, o apelido atual.
- Expor `nickname` (nullable) nos espécimes de: cartas das trocas, feed
  (aberturas e trocas) e comparar.

### Front

- Ficha (`SpecimenSheet.vue`):
  - O apelido aparece em destaque e o nome da espécie fica menor
    embaixo. Sem apelido, fica como hoje.
  - Com `canName`, aparece o botão "Dar apelido": abre um campo com
    contador de 16 e, depois, a confirmação com aviso de definitivo.
  - No Histórico, cada linha ganha "· chamado **João**" quando houver
    apelido.
- Trocas, feed e comparar: o apelido no lugar do nome, com a espécie
  menor, quando houver.
- A grade da coleção não muda, porque é por espécie e não por espécime.

## 2. Giroscópio

- `resources/js/lib/deviceTilt.ts`: um único listener de
  `deviceorientation` pra página toda, ligado só enquanto alguém usa e
  com a preferência ligada.
  - **Calibração**: a primeira leitura vira o "reto", porque ninguém
    segura o celular deitado. Recalibra devagar (filtro passa-baixa), pra
    que mudar a pegada não deixe a carta torta pra sempre.
  - A cada `requestAnimationFrame`, escreve `--gx` e `--gy` (de -1 a 1)
    no `:root`.
- **Grade da coleção**: só o brilho do holo segue `--gx`/`--gy`, por CSS.
  As cartas não inclinam e não roda JS por carta.
- **Carta grande** (a ficha e a carta da cerimônia do pack): o
  `v-card-tilt` aceita o sensor como fonte, além do mouse. Inclinação
  completa até `MAX_DEG`, com o mesmo `--mx`/`--my`/`--tilt` de hoje.
- `resources/js/lib/motionPreference.ts`, no mesmo molde do
  `soundPreference.ts` (localStorage com try/catch):
  - O padrão é ligado quando o aparelho não exige permissão (Android) e
    desligado quando exige (iOS, com
    `DeviceOrientationEvent.requestPermission`).
  - Ligar no iOS chama `requestPermission()` dentro do toque. Se for
    negado, o interruptor volta pra desligado.
- Com `prefers-reduced-motion: reduce`, nada se mexe, qualquer que seja a
  preferência.
- O sensor só funciona em `https`. Pela rede local, testar no S26+ com
  `chrome://flags/#unsafely-treat-insecure-origin-as-secure` e o IP do
  PC. No iPhone, só depois do deploy.
- Só efeito visual: o cliente não decide nada (regra 3).

## 3. Cadastro e Ajustes

- Onboarding: passo novo **Opções** entre nickname e data de nascimento.
  A ordem fica nickname → Opções → data de nascimento → avatar. O passo
  tem dois interruptores (`AppToggle`), "Som" e "Mexer as cartas com o
  celular". Não manda nada pro servidor: as duas preferências ficam no
  aparelho.
- Ajustes (`settings/Show.vue`): interruptor de movimento ao lado do de
  som.

## Testes (Pest)

- `NameSpecimen`: o dono dá o apelido; outro jogador é recusado; não dá
  pra dar um segundo apelido; o valor é aparado; vazio e com 17
  caracteres são recusados (pelo request).
- Troca aceita: o apelido vai pro `specimen_transfers.nickname`, o
  espécime fica sem apelido e o novo dono consegue dar outro.
- Banimento: o apelido vai pro histórico e o espécime libertado fica sem
  apelido.
- Ficha: `nickname`, `canName` e `specimenNickname` corretos no
  histórico de um espécime trocado duas vezes.
- O giroscópio não tem teste de back. Validar no navegador (emulando
  `deviceorientation` no Playwright) e no S26+ com a flag.

## Divisão

- **Kimi**: backend inteiro (migration, Action, request, rota, troca,
  banimento, ficha, campos nos payloads) e os testes em Pest.
- **Claude**: front (ficha, trocas, feed, comparar, `deviceTilt`,
  `motionPreference`, onboarding, Ajustes), revisão do Kimi e o
  `ci:check` final.
