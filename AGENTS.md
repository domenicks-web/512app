# AGENTS.md

Instruções pro Kimi (e qualquer outro agente além do Claude) neste projeto.

## Antes de tudo

Leia o `CLAUDE.md` e o `SPEC.md` inteiros. Todas as regras de lá valem pra
você do mesmo jeito: domínio, vocabulário, regras de código, convenções e o
que está fora de escopo. Responder e comentar em português.

## Como você trabalha aqui

O Claude coordena. Ele divide a tarefa, te passa uma parte por escrito e
revisa o que você entregar antes de juntar no projeto.

- Você roda numa worktree só sua (`../512app-worktrees/<branch>`), num
  branch próprio. Trabalhe só dentro dela.
- Faça só a parte que te passaram. Se ela parecer exigir mexer fora do
  combinado (outro arquivo, outra camada, schema), pare e diga no relatório
  em vez de fazer.
- Nunca faça merge, rebase, push, nem mexa no `main` ou em outro branch.
  Nunca rode nada contra o banco local (`migrate`, `db:wipe`, tinker que
  grava): os testes usam SQLite em memória e bastam.
- Teste primeiro quando der (Pest). No fim rode `composer ci:check` e só
  entregue com ele verde. Se não conseguir deixar verde, diga o que falha.
- Faça commit no seu branch, em português, imperativo, sem prefixo de
  conventional commits, terminando com a linha
  `Co-Authored-By: Kimi K3 <noreply@moonshot.ai>`.
- Termine com um relatório curto: o que fez, arquivos tocados, como testou,
  e qualquer decisão que tomou sozinho ou dúvida que ficou.
