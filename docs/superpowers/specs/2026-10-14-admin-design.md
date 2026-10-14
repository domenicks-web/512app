# Admin de verdade — design

Fonte: `design_handoff_512_onboarding/ADMIN.md` + `512 Admin.dc.html` (alta
fidelidade). Este documento registra só onde o projeto diverge ou completa o
handoff. O resto segue o `ADMIN.md` à risca.

## Decisões

- **Branch `admin` a partir de `perfil`** (a liberação de data de nascimento
  mora lá). Não depende do merge do `perfil`.
- **`invite_redemptions` continua** no lugar de `invites.used_by` — já resolve
  "quem usou" com `max_uses > 1`. Nada a remover.
- **Sem convite infinito.** `max_uses` vira obrigatório, faixa 1–10
  (`config('game.invites.max_uses')`). O `--unlimited` do comando antigo morre.
- **Validade:** `7`, `30` ou `never` (opções em config).
- **Status derivado na ordem do handoff:** revogado → esgotado → expirado →
  ativo (hoje expirado vence esgotado; muda). `Invite::scopeActive()` reproduz
  a mesma regra em SQL; o resgate no cadastro continua com `lockForUpdate`.
- **Código `PREFIXO-XXXX`:** prefixo digitado (`[A-Z0-9]{1,8}`, default =
  nickname do admin normalizado), sufixo de 4 no alfabeto
  `ABCDEFGHJKLMNPQRSTUVWXYZ23456789`.
- **Sufixo sorteado no servidor:** `GET /admin/convites/sufixo` devolve um
  sufixo e guarda na sessão (mesmo padrão da prévia de tag do onboarding). O
  `POST` usa o sufixo da sessão; se `PREFIXO-SUFIXO` já existir, sorteia outro
  e devolve o código final. Os caracteres girando no caça-níquel são só ruído
  visual, nunca viram código. "Copiar" antes de criar copia a prévia.
- **Gate `admin`** (`can:admin`) substitui o middleware `admin`.
  `onboarded` continua no grupo.
- **Rotas renomeadas:** `/admin/usuarios` vira `/admin/jogadores`.
- **Avatar do jogador = sprite real** (`TrainerAvatar`), não a inicial
  colorida do protótipo. Selo estrela do admin por cima.
- **Variações do protótipo não vão pra produção:** confirmação sempre
  digitada, densidade confortável.
- **Menu "⋯" por jogador** (coluna extra no fim da tabela) com:
  - *Liberar correção da data de nascimento* (já existe, só muda de lugar;
    desabilitado se não está travada).
  - *Reenviar email de confirmação* (só se o email não foi confirmado).
  - *Trocar senha*: modal no estilo do modal de papel, com senha nova e
    confirmação. Derruba **todas** as sessões do jogador, troca o
    `remember_token` e manda o `PasswordChangedMail`. Não pode ser usado em si
    mesmo (pra isso existe `/perfil`).
- **Fontes:** JetBrains Mono 800 entra no `vite.config.ts` (bunny), token
  `--font-mono`.

## Schema

- `users.invite_quota` smallint unsigned, default `config('game.invites.default_quota')` (2).
  Só armazenada; jogador comum gerar convite está fora do escopo.
- `invites.revoked_by` fk `users`, nullable.
- `invites.max_uses` deixa de ser nullable (default 1).

## Actions (`app/Actions/Admin/`)

| Action | Regras |
|---|---|
| `CreateInvite(User $by, CreateInviteData)` | prefix + suffix pedido; colisão sorteia outro. |
| `GenerateInviteSuffix` | `random_int` no alfabeto do config. |
| `RevokeInvite(User $by, Invite)` | Só ativo (senão `InviteNotRevocableException`, 422). Grava `revoked_at`/`revoked_by`. |
| `SetInviteQuota(User $target, int)` | 0–9; alvo admin → exceção (422). |
| `SetAdmin(User $actor, User $target, bool)` | Alvo = actor → 403. Remover último admin → 422 (lock nos admins). Junta `GrantAdmin` + `RevokeAdmin`. |
| `UnlockBirthdateCorrection` | Já existe. |
| `ResendVerificationEmail(User)` | No-op se já confirmado. |
| `SetPlayerPassword(User $actor, User $target, string)` | Alvo = actor → 403. |

A confirmação digitada (`confirm: "Nick#1234"`) é validada no FormRequest do
papel, case-insensitive com trim.

## Rotas (`auth`, `onboarded`, `can:admin`)

| Método | Rota | Nome |
|---|---|---|
| GET | `/admin/convites` | `admin.invites.index` |
| GET | `/admin/convites/sufixo` | `admin.invites.suffix` (JSON) |
| POST | `/admin/convites` | `admin.invites.store` |
| POST | `/admin/convites/{invite}/revogar` | `admin.invites.revoke` |
| GET | `/admin/jogadores?q=&filtro=admins` | `admin.players.index` |
| PATCH | `/admin/jogadores/{user}/cota` | `admin.players.quota` |
| PATCH | `/admin/jogadores/{user}/papel` | `admin.players.role` |
| POST | `/admin/jogadores/{user}/liberar-nascimento` | `admin.players.birthdate` |
| POST | `/admin/jogadores/{user}/reenviar-confirmacao` | `admin.players.verification` |
| PUT | `/admin/jogadores/{user}/senha` | `admin.players.password` |

Busca: `nickname`, `nickname#tag` e `email` com `like` (o banco local é
MySQL, CI é SQLite; ambos case-insensitive no `like`). O filtro de status dos
convites e a busca de jogadores rodam no cliente sobre a lista inteira (grupo
pequeno, entre amigos), o `q` também vai pra URL pra sobreviver ao F5.

## Comandos

`admin:promote`, `admin:demote`, `invite:create {--prefix=} {--uses=1}
{--days=30|--never} {--by=}`, `invite:revoke {code}`, `invite:list
{--status=}`. Alvo aceita `email` ou `Nick#1234`. Todos chamam as mesmas
Actions. `invites:create` sai.

## Front

- `pages/admin/Invites.vue` e `pages/admin/Players.vue` (o `Users.vue` morre),
  com `AdminShell` novo (header com logo, abas em pílula com contadores,
  usuário à direita, link "Voltar ao jogo" e "Sair" num menu do avatar).
- Componentes em `components/admin/`: `InviteGenerator` (caça-níquel),
  `AdminToast`, `RoleDialog`, `PasswordDialog`, `PlayerMenu`, `QuotaStepper`,
  `StatusPill`.
- Cota e papel com atualização otimista do Inertia v3 e rollback no erro.
- Só tema claro (como o fluxo de entrada).
- Responsivo: tabelas rolam dentro do card abaixo de ~850px.

## Testes (Pest)

Actions: colisão de código, sufixo da sessão, faixas, revogar só ativo,
último admin, rebaixar a si mesmo (concorrência via lock), cota em admin,
senha derruba sessões e manda email, reenviar só se não confirmado.
Cadastro: resgate só de convite ativo (revogado, esgotado, expirado
recusados). HTTP: Gate barra não admin em todas as rotas, confirmação
digitada errada dá 422, busca. Comandos: os cinco.
