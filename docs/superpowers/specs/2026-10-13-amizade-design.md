# Amizade de verdade — design

Data: 13/10/2026. Substitui o "amigos = todo mundo do grupo" do subprojeto B.

## Objetivo

Os jogadores passam a ter uma lista de amigos própria, formada por pedidos de
amizade. Ranking e feed continuam com o grupo inteiro. Só a tela Amigos e o
"amigos online" da Home passam a considerar amizade.

## Decisões do usuário

- **Escopo da amizade (B):** só a tela Amigos e o "amigos online" ficam
  restritos a amigos. Ranking e feed continuam com o grupo inteiro.
- **Como adicionar (A + C):** pedido só por `nickname#tag` digitado. Quem
  criou o convite de alguém vira amigo dessa pessoa automaticamente.
- **Aviso de pedido (A):** seção "Pedidos recebidos" na tela Amigos, com
  um contador vermelho no ícone Amigos do dock.
- **Online:** janela de 2 minutos, com a Home e a tela Amigos atualizando a
  contagem a cada 60 segundos.

## Dados

Tabela nova `friendships`:

| coluna         | tipo                                  |
| -------------- | ------------------------------------- |
| `id`           | bigint                                |
| `requester_id` | fk `users`, quem mandou o pedido      |
| `addressee_id` | fk `users`, quem recebeu              |
| `status`       | enum `FriendshipStatus`: `pending`, `accepted` |
| `accepted_at`  | timestamp, nullable                   |
| `created_at`, `updated_at` |                           |

- `unique (requester_id, addressee_id)`; índice em `addressee_id, status`.
- O par é único nas duas direções. Essa regra é garantida na Action, que
  roda em transação e checa os dois sentidos antes de inserir.
- Uma linha `pending` é um pedido; a mesma linha vira amizade quando passa
  pra `accepted`.
- Recusar, cancelar e desfazer **apagam a linha**. Não existe status
  `declined`; a pessoa pode mandar pedido de novo depois.
- `down()` derruba a tabela.

`User` ganha:

- `friendIds(): list<int>`: ids das amizades `accepted`, nas duas direções.
- relações `sentFriendRequests` e `receivedFriendRequests`.

## Regras (Actions em `app/Actions/Friends/`)

### `SendFriendRequest::handle(User $sender, string $handle): Friendship`

`$handle` vem como `nickname#tag`. Na ordem:

1. Formato inválido (sem `#`, tag sem 4 dígitos): erro "Use o formato
   nickname#1234".
2. Ninguém com esse par (só usuário com perfil completo): erro "Não achei
   ninguém com esse nickname#tag".
3. É o próprio remetente: erro "Esse é você".
4. Já são amigos: erro "Vocês já são amigos".
5. O remetente já mandou pedido pendente pra essa pessoa: erro "Você já
   mandou um pedido pra essa pessoa".
6. A outra pessoa já tinha mandado pedido pendente pro remetente: **aceita
   esse pedido** (vira `accepted`) e devolve ele.
7. Senão cria `pending`.

O nickname é comparado sem diferenciar maiúscula de minúscula, com
`lower()` explícito dos dois lados. Assim o resultado é igual no MySQL e no
SQLite dos testes.

Erros saem como `ValidationException` no campo `handle`.

### `AcceptFriendRequest::handle(User $user, Friendship $request): Friendship`

Só o `addressee` aceita, e só pedido `pending`. Muda pra `accepted` e grava
`accepted_at`.

### `RemoveFriendship::handle(User $user, Friendship $friendship): void`

Apaga a linha. Serve pra recusar (pelo addressee, quando `pending`),
cancelar (pelo requester, quando `pending`) e desfazer (por qualquer um dos
dois, quando `accepted`).

### Autorização

`FriendshipPolicy`:

- `accept`: o usuário é o addressee e o status é `pending`.
- `delete`: o usuário é o requester ou o addressee.

Fora disso a resposta é 403.

### Amizade com quem convidou

`CompleteOnboarding`, ao terminar, chama `BefriendInviter`. Se
`users.invited_by` aponta pra um convite, cria amizade `accepted` entre o
jogador e `invites.created_by`. Nada acontece quando é a mesma pessoa,
quando já existe linha entre os dois (nesse caso vira `accepted`) ou quando
não há convite. O `CompleteOnboarding` passa a rodar em transação, pra
perfil e amizade gravarem juntos.

## Rotas (`auth` + `onboarded`)

| método | url                              | o que faz          |
| ------ | -------------------------------- | ------------------ |
| GET    | `/amigos`                        | tela (já existe)   |
| POST   | `/amigos/pedidos`                | enviar pedido      |
| POST   | `/amigos/pedidos/{friendship}/aceitar` | aceitar      |
| DELETE | `/amigos/{friendship}`           | recusar, cancelar ou desfazer |

Os controllers são finos: FormRequest, Action e `back()`. Os erros voltam
pelo `errors` do Inertia.

## Dados pras telas

- **`BuildFriendList`** substitui o `BuildGroupRoster` e devolve:
  - `friends`: amigos com `nickname`, `tag`, avatar, `ownedSpecies` e
    `isOnline`, com online primeiro e depois por nickname;
  - `incoming`: pedidos recebidos pendentes, com quem mandou e quando;
  - `outgoing`: pedidos enviados pendentes, com pra quem e quando.
- **Contador do dock**: prop compartilhada `pendingFriendRequests`, com a
  contagem de pedidos recebidos pendentes, no `HandleInertiaRequests`. Ela é
  uma closure, então só roda quando alguém está logado.
- **Home**: `friendsOnline` passa a contar só os amigos que estão online.

## Online

- `game.group.online_window_minutes` passa de 5 pra **2**.
- Novo `game.group.presence_poll_seconds = 60`.
- A Home usa `usePoll` só com `friendsOnline` e a tela Amigos só com
  `friends`, os dois a cada `presence_poll_seconds`, que vem como prop. Cada
  atualização é uma requisição, então ela também atualiza o `last_activity`
  da sessão e serve como o "ping".

## Telas

**Amigos:**

1. **Topo:** campo "Adicionar amigo" (`nickname#tag`) com botão. O erro
   aparece na pill de erro do design (vermelho-claro), e o sucesso limpa o
   campo.
2. **Pedidos recebidos**, se houver: avatar, nickname e "Aceitar" (botão
   primário) e "Recusar" (secundário).
3. **Pedidos enviados**, se houver: nickname#tag, "há X min" e "Cancelar".
4. **Amigos:** a grade atual, com o ponto online. Cada cartão tem um botão
   discreto "Desfazer amizade", que troca o cartão por uma confirmação na
   própria tela ("Desfazer amizade com Lin?" Sim / Não). Não usa caixa de
   diálogo do navegador.
5. **Estado vazio sem amigos:** "Manda um pedido pelo nickname#tag de alguém
   do grupo".

**Dock:** um contador vermelho no canto do ícone Amigos (fundo
`--color-brand`, texto branco, 18px), visível quando há pedido recebido.

## Testes (Pest)

- **Enviar pedido:** sucesso, formato inválido, pessoa inexistente, você
  mesmo, já amigos, pedido duplicado e aceite automático quando os dois
  mandaram pedido.
- **Aceitar:** só o addressee consegue; um terceiro e o requester tomam 403.
- **Remover:** recusar, cancelar e desfazer funcionam; um terceiro toma 403.
- **Onboarding:** cria amizade com quem convidou e não duplica se já
  existir linha.
- **Tela Amigos:** lista só amigos e separa os pedidos.
- **Home:** `friendsOnline` conta só amigos.
- **Prop compartilhada:** `pendingFriendRequests` conta só os pedidos
  recebidos pendentes.

## Fora de escopo

Bloquear alguém, notificação em tempo real (Reverb, fase 4), perfil público
do amigo (fase 3) e lista de sugestões de amizade.

## Também muda

- `SPEC.md`: a tabela `friendships` entra no schema, com as regras de
  amizade na seção de cadastro.
- `CLAUDE.md`: atualizar o checkpoint ("amigos = grupo" deixa de valer).
