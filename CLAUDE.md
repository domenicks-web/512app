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

## Estado atual (15/10/2026)

O histórico completo (o que foi feito, decidido e testado, sessão a sessão)
está em `docs/checkpoint.md`. Ler lá quando precisar do porquê de algo.

- **Feito e no `main`**: fases 1 a 4C. Cadastro por convite e onboarding,
  pack com provably fair e `/verificar`, coleção, ranking, feed, amigos,
  Cartão de Treinador, comparar, trocas (4A), notificações em tempo real
  pelo Reverb (4B), suspensão e banimento (4C: `sanctions`, banido tem os
  espécimes libertados pro "Professor Carvalho", `users.is_system`; quem
  tem sanção ativa some de tudo pelo `User::active()`), admin (`/admin`,
  sem atalho na UI).
- **Pendências (15/10, 18h)**:
    1. **Loja e economia**: brainstorm em andamento (decisões e onde
       paramos no fim de `docs/checkpoint.md`). Próximo: nomes dos irmãos
       da Poké Mart, depois números, spec e SPEC atualizado. Nada de
       código antes da spec aprovada.
    2. Deploy (VPS/Oracle free tier; Reverb como processo próprio).
    3. PWA por último (instalar no celular exige `https`: só com deploy).
    4. Apagar o branch local `fase5` (já está no `main`).
- **Decidido em 15/10 à tarde**: feed só com shiny e lendário (raro
  saiu); selo "Nº 1" virou a plaquinha dourada `FirstMintSeal`; dock de
  7 botões aprovado no celular; `SPEC.md` com fases 1–4 marcadas.
- **Descartado (15/10)**: arrastar pro lado pra trocar de tela no celular
  (muito trabalho e risco de usabilidade ruim). Não sugerir de novo.
- **Fora de escopo até atualizar o SPEC**: moeda/dust, loja, dinheiro
  real (ideias do usuário, anotadas no histórico). Junto da loja, no
  futuro: vender "renomear de novo" o apelido do espécime.

### Ambiente e contas

- MySQL no `docker compose up -d`; `composer run dev` sobe app, Vite,
  fila, logs e o Reverb (8080). Rede local: `SERVER_HOST=0.0.0.0`,
  `DEV_LAN_HOST` e `APP_URL` com o IP do PC (hoje 192.168.1.26). O usuário
  testa no S26+ por `http://192.168.1.26:8000`. Mudou o `.env`? Reiniciar
  o dev.
- Conta de teste: `claudetest@512app.com` / `claude` (`Claude#9805`).
  `Kimi#2398` (`claudetest2@512app.com`) está **banida** (teste da 4C).
  O `.env` manda email de verdade (Gmail SMTP): conta de teste nova deve
  usar domínio inexistente (`@512app.local`). A do
  usuário é a Lin (`Lin#0741`, admin). Liberar pack: `/admin/jogadores` →
  ⋯ ou `GrantNextPack`.
- Navegador: o MCP do Playwright não acha o Chrome; usar script Node com o
  `playwright` do cache do npx e `executablePath` no
  `chromium_headless_shell-1243` (`~/.cache/ms-playwright`). Com rota
  interceptada, passar `--disable-features=...LocalNetworkAccessChecks`.
- `composer ci:check` reproduz o CI inteiro (PHP 8.4, SQLite). Formatar o
  front com `npx vp check --fix` (o `npx prettier` solto usa config errada).

### Armadilhas que já custaram caro

- `<Form>` do Inertia: `:action="store()"`, nunca `v-bind="store()"`.
- SSR do Inertia não injeta `Teleport`: montar só depois do `onMounted`.
- CSS novo fora de `@layer` ganha das utilities do Tailwind 4.
- Telas novas forçam o tema claro (`.entry-light`); em componente que pode
  aparecer fora dele, usar cor fixa, não token (`text-ink` some no escuro).
- Layout crítico em Tailwind, não em `<style>` (no celular o CSS dos
  componentes vinha de `localhost:5173`; o plugin `lanSsrOrigin` resolve).
- Sprite de espécie sempre pelo `FittedSprite`, nunca `<img>` solto.
  Sprites não seguem o mouse (pedido do usuário; não recolocar).
- Wayfinder tipa `{species}` diferente no MySQL e no SQLite: usar
  `speciesKey()`.

### Trabalho em dupla com o Kimi K3

Kimi Code CLI (`kimi -m kimi-code/k3`). O Claude divide o trabalho, cria a
worktree com `scripts/kimi-worktree.sh <branch> [base]` e chama
`cd <worktree> && KIMI_CODE_TRUST_WORKSPACE=1 kimi -m kimi-code/k3 -p "..."`.
Tudo do Kimi passa pela revisão do Claude e pelo `ci:check` antes de
juntar. Pra não gastar token à toa: um plano só com a parte dele, código
pronto, só os testes da task no meio e o `ci:check` uma vez no fim.
