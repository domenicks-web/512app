# Fase 4C: suspensão e banimento

Decidido com o usuário em 15/10/2026, em duas partes aprovadas no chat.

## Regras

- **Suspensão**: dura um dos prazos de `game.sanctions.suspension_days`
  (1, 3, 7, 30). Os espécimes ficam congelados com o dono. Acaba sozinha no
  prazo (calculado na hora, sem agendador). O admin pode encerrar antes.
- **Banimento**: permanente, sem desfazer pela UI. Os espécimes são
  **libertados**: passam pra conta do sistema "Professor Carvalho"
  (`users.is_system`, não loga, não troca, não aparece em lugar nenhum), com
  uma transferência sem troca (`specimen_transfers.trade_id` nulo) que a
  ficha mostra como "Libertado". As amizades e os pedidos dele são apagados.
- **Mint nunca é reaproveitado.** Libertar só muda o dono.
- **Nas duas**: o motivo é obrigatório; todas as sessões caem e o remember
  token é trocado; as trocas abertas dele (enviadas e recebidas) são
  canceladas como `unavailable`, e quem tinha proposto pra ele recebe o
  aviso `trade_fallen`; vai um email com o motivo (layout `<x-mail-layout>`).
- **Some de tudo** enquanto a sanção estiver ativa: ranking (todas as
  abas, inclusive a da Home), amigos dos outros (`friendIds`, pedidos),
  busca por `nick#tag`, Cartão de Treinador e ficha dele (404), comparar
  (404) e a troca com ele. O feed antigo fica (é história). A conta do
  sistema também nunca aparece nesses lugares.
- **Bloqueio**: um middleware no grupo `web` desloga quem tem sanção ativa e
  manda pro login com a mensagem no flash `sanction`. O login recusa com a
  mesma mensagem ("Sua conta está suspensa até 18/10 às 14:00. Motivo: …" /
  "Sua conta foi banida. Motivo: …").
- **Quem pode**: só admin; nunca a si mesmo, outro admin (tirar o papel
  antes), a conta do sistema ou quem já tem sanção ativa (exceto banir quem
  está suspenso: a suspensão é encerrada e o banimento aplicado).
- **Conta fake que repassou espécimes**: fora de escopo por enquanto.

## Dados

- `sanctions`: `user_id`, `issued_by` (nulo quando vem de comando),
  `type` (`App\Enums\SanctionType`: `suspension`, `ban`), `reason`,
  `ends_at` (nulo no ban), `lifted_at`, `lifted_by`, timestamps.
  "Ativa" = não encerrada e (ban ou `ends_at` no futuro).
- `users.is_system` (boolean, padrão false) e a conta do sistema criada
  por migration.
- `specimen_transfers.trade_id` passa a aceitar nulo (libertação).

## Admin

- `/admin/jogadores`: cada jogador traz `sanction` (`type`, `endsAt`,
  `reason`) ou nulo, e `specimenCount`. O menu ⋯ ganha "Suspender"
  (pílulas de prazo + motivo) e "Banir" (motivo + confirmação digitada
  `Nick#1234`, avisando que liberta N espécimes). Suspenso aparece com a
  pílula "Suspenso até 18/10" e a ação "Encerrar suspensão"; banido aparece
  com a pílula "Banido" e sem ações. A conta do sistema não é listada.
- Rotas: `POST admin/jogadores/{user}/suspender`, `.../banir` e
  `.../encerrar-suspensao`.
- Comandos: `admin:suspend {player} {days} {reason}`,
  `admin:ban {player} {reason}` e `admin:lift {player}`.

## Divisão

1. **Kimi** (plano `docs/superpowers/plans/2026-10-15-banimento-kimi.md`):
   todo o backend e os testes.
2. **Claude**: os diálogos e a pílula no admin, a mensagem do flash no
   login, a linha "Libertado" na ficha, e depois a junção, a revisão e o
   teste ponta a ponta.
