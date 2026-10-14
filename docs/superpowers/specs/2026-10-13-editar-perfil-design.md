# Editar perfil — design

Data: 13/10/2026.

## Objetivo

Uma tela onde o jogador altera avatar, nickname, data de nascimento, email e
senha, com regras que impedem abuso (nickname a cada 15 dias, data corrigida
uma vez) e protegem a conta (senha atual pra email/senha, confirmação no
email novo, avisos por email estilizados).

## Decisões do usuário

- **Email (C):** link de confirmação pro email novo; só troca depois do
  clique. Aviso pro email antigo quando a troca acontece. Aviso por email
  quando a senha muda. Emails **estilizados** no visual do jogo. Envio via
  **SMTP do Gmail** (conta criada pelo usuário; senha de app no `.env`).
- **Data de nascimento (B):** pode ser corrigida **uma vez**; depois trava.
  A tela deixa isso claro com um texto antes de salvar.
- **Nickname:** alterou, só pode alterar de novo depois de **15 dias**.
- **Senha:** "confirmação forte" + popup de confirmação.
- **Avatar:** pode trocar.

## Decisões tomadas pelo Claude (revisar)

1. **Onde fica:** `/perfil`, aberto pelo item "Editar perfil" no menu
   Ajustes do dock. Usa a moldura `GroupScreen` com o item Ajustes ativo.
2. **Avatar:** as mesmas 15 espécies do onboarding
   (`game.onboarding.avatar_species_ids`), sem limite de troca.
3. **Tag ao trocar nickname:** mantém a tag atual se o par
   `(nickname novo, tag)` estiver livre; senão sorteia outra
   (`GenerateUserTag`). A tela avisa que a tag pode mudar e que os amigos
   continuam amigos (amizade é por id).
4. **Contagem dos 15 dias:** coluna `users.nickname_changed_at`. O nickname
   escolhido no onboarding **não** conta — a primeira troca é livre.
   Trocar só maiúscula/minúscula conta como troca. Mesmo nickname = erro.
5. **Data travada:** coluna `users.birthdate_corrected_at`. Pra destravar,
   o admin aperta "Liberar correção de data" em `/admin/usuarios` (zera a
   coluna) e o jogador corrige de novo pela tela.
6. **"Confirmação forte" da senha:**
   - senha atual obrigatória;
   - senha nova digitada duas vezes;
   - regra `Password::defaults()` (forte em produção: 12+, maiúscula,
     minúscula, número, símbolo, não vazada) e a lista de requisitos
     visível na tela;
   - senha nova diferente da atual;
   - popup próprio do app (não o do navegador) antes de enviar:
     "Trocar a senha? Você vai sair dos outros aparelhos.";
   - depois da troca: encerra as outras sessões do jogador e manda o email
     "sua senha foi alterada".
7. **Popup próprio também** pra trocar nickname (avisa dos 15 dias e da tag)
   e pra corrigir a data (avisa que é uma vez só).
8. **Email:**
   - pedir troca exige a senha atual;
   - o email novo vai pra `users.pending_email` e recebe um link assinado
     com validade de **60 minutos** (`game.profile.email_change_link_minutes`);
   - o link só funciona logado como o mesmo jogador; ao abrir, troca o
     email, marca `email_verified_at = now()`, limpa `pending_email` e
     manda o aviso pro email **antigo** com o novo mascarado
     (`r***@gmail.com`);
   - email novo é checado como único no pedido **e** de novo no clique
     (alguém pode ter cadastrado nesse meio-tempo);
   - a tela mostra "Esperando confirmação em x@y" com "Cancelar troca"; um
     pedido novo substitui o anterior (link antigo deixa de valer porque o
     link leva o email no payload assinado e só vale se bater com
     `pending_email`).
9. **Limite de tentativas:** `throttle:6,1` nas rotas que pedem senha atual
   (senha e email).
10. **Envio:** síncrono (sem fila), com `Mailable`s e um layout Blade de
    email com CSS inline nas cores do jogo (vermelho `#f24d4d`, tinta
    `#2b2f36`, fundo `#eff0f2`, Inter com fallback de sistema). Em dev o
    `MAIL_MAILER=log` continua; o `.env.example` ganha o bloco do Gmail.

## Dados

Migration nova em `users` (com `down()`):

| coluna                   | tipo                |
| ------------------------ | ------------------- |
| `nickname_changed_at`    | timestamp, nullable |
| `birthdate_corrected_at` | timestamp, nullable |
| `pending_email`          | string, nullable    |

`config/game.php`, bloco novo `profile`:

```php
'profile' => [
    'nickname_cooldown_days' => 15,
    'email_change_link_minutes' => 60,
],
```

## Rotas (`auth` + `onboarded`)

| método | url                         | nome                          |
| ------ | --------------------------- | ----------------------------- |
| GET    | `/perfil`                   | `profile.edit`                |
| PUT    | `/perfil/avatar`            | `profile.avatar.update`       |
| PUT    | `/perfil/nickname`          | `profile.nickname.update`     |
| PUT    | `/perfil/nascimento`        | `profile.birthdate.update`    |
| POST   | `/perfil/email`             | `profile.email.store` (pedir) |
| DELETE | `/perfil/email`             | `profile.email.destroy` (cancelar) |
| GET    | `/perfil/email/confirmar`   | `profile.email.confirm` (assinada) |
| PUT    | `/perfil/senha`             | `profile.password.update`     |
| POST   | `/admin/usuarios/{user}/liberar-nascimento` | `admin.users.birthdate.unlock` |

Todas com FormRequest → Action → `back()` (a confirmação de email redireciona
pra `/perfil` com flash de sucesso).

## Actions (`app/Actions/Profile/`)

- `UpdateAvatar::handle(User, int $speciesId)`
- `ChangeNickname::handle(User, string $nickname, CarbonInterface $now)` —
  erro se ainda no cooldown ("Você poderá trocar de novo em DD/MM") ou se
  igual ao atual; resolve a tag; grava `nickname_changed_at`.
- `CorrectBirthdate::handle(User, CarbonInterface $birthdate, CarbonInterface $now)`
  — erro se `birthdate_corrected_at` já preenchido.
- `RequestEmailChange::handle(User, string $newEmail): void` — grava
  `pending_email`, manda `ConfirmNewEmailMail` pro email novo.
- `ConfirmEmailChange::handle(User, string $signedEmail): void` — confere
  `pending_email === $signedEmail` e unicidade, troca, manda
  `EmailChangedMail` pro antigo.
- `CancelEmailChange::handle(User): void`.
- `ChangePassword::handle(User, string $newPassword, string $currentSessionId): void`
  — grava, apaga as outras linhas de `sessions` do jogador, manda
  `PasswordChangedMail`.
- `Admin\UnlockBirthdateCorrection::handle(User): void`.

A validação de senha atual fica no FormRequest (`current_password`).

## Tela `/perfil`

Coluna de cartões brancos (mesmo visual de Amigos/Trocas):

1. **Treinador:** avatar grande + grade de parceiros (`PartnerGrid`) com
   "Salvar avatar"; campo nickname com `#tag` ao lado, texto de ajuda com a
   regra dos 15 dias (ou "Você poderá trocar de novo em DD/MM" travado).
2. **Data de nascimento:** as rodas do onboarding (`BirthdateWheels`) e o
   texto "Você só pode corrigir a data de nascimento uma vez. Depois disso,
   só um admin consegue liberar outra correção." Travada mostra a data e
   "Já corrigida. Fale com um admin se precisar mudar de novo."
3. **Email:** email atual (com selo "não confirmado" se for o caso), campo
   email novo + senha atual. Com troca pendente: "Enviamos um link pra
   x@y. Ele vale por 60 minutos." + "Cancelar troca".
4. **Senha:** senha atual, nova, repetir nova, lista de requisitos, botão
   que abre o popup.

Componente novo `components/group/ConfirmDialog.vue` (diálogo modal próprio:
`role="dialog"`, `aria-modal`, foco preso, Esc fecha, fundo escurecido).

## Emails

Layout `resources/views/mail/layout.blade.php` (CSS inline, largura 560,
cabeçalho vermelho com "512", rodapé "Você recebeu porque tem conta no 512").
Três `Mailable`s em `app/Mail/`:

- `ConfirmNewEmailMail` — botão "Confirmar email novo", validade.
- `EmailChangedMail` — "Seu email foi alterado para r***@gmail.com. Não foi
  você? Fale com um admin."
- `PasswordChangedMail` — "Sua senha foi alterada em DD/MM às HH:MM."

## Testes (Pest)

- Avatar: troca; recusa espécie fora da lista.
- Nickname: troca e grava data; primeira troca livre; bloqueia dentro dos
  15 dias com a data de liberação; libera no dia 15; mesmo nickname dá erro;
  mantém tag quando livre; troca tag quando ocupada.
- Data: corrige uma vez; segunda vez dá erro; admin libera e dá pra corrigir
  de novo; só admin libera.
- Email: pedir exige senha certa; grava pendente e manda o email (Mail
  fake); recusa email já usado; link confirma, verifica e avisa o antigo;
  link com email diferente do pendente não troca; link expirado dá 403;
  email tomado no meio-tempo não troca; cancelar limpa.
- Senha: exige senha atual; exige confirmação; recusa igual à atual; troca,
  apaga outras sessões e manda o aviso; throttle.
- Tela: props (opções de avatar, data de liberação do nickname, data
  travada, email pendente).

## Fora de escopo

Recuperação de senha ("esqueci a senha") e estilizar o email de
verificação do cadastro — anotar nas pendências.
