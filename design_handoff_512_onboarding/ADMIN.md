# Handoff: 512 — Painel Admin (Convites e Jogadores)

## Visão geral
Painel restrito a admins para controlar quem entra no 512. Duas abas: **Convites** (gerar, copiar, revogar códigos) e **Jogadores** (ver quem entrou por qual código, ajustar cota de convites, promover/rebaixar admin). Regras de produto em `SPEC.md` (seção 4, "Sobre o cadastro").

## Sobre os arquivos de design
`512 Admin.dc.html` é uma **referência de design em HTML**, não código de produção. Recrie em **Laravel 12 + Inertia + Vue 3 + Tailwind** (stack do SPEC), seguindo os padrões do projeto. Abra o arquivo no navegador com `support.js` na mesma pasta. Os dados do protótipo são mock (constantes `USERS` e `INVITES` na classe `Component`).

## Fidelidade
**Alta fidelidade.** Cores, tipografia, raios, sombras e copy são finais. Usa os mesmos tokens do `README.md`.

---

## Mudanças de schema (além do SPEC)

```sql
users
  + is_admin               boolean, default false
  + invite_quota           smallint, default 2     -- ignorada se is_admin

invites
  + revoked_at             timestamp, nullable
  + revoked_by             fk users, nullable
  - used_by                -- remover: com max_uses > 1 não cabe numa coluna.
                           -- quem usou = users where invited_by = invites.id
```

**Status do convite** é derivado, nunca gravado. Ordem de precedência:
1. `revoked_at` não nulo → `revogado`
2. `uses_count >= max_uses` → `esgotado`
3. `expires_at < now()` → `expirado`
4. senão → `ativo`

Implementar como accessor `Invite::status()` + scope `Invite::active()`. O resgate no cadastro só aceita `active()`, dentro de transação com `lockForUpdate()` incrementando `uses_count`.

---

## Comandos (Artisan)

Necessários para bootstrap e para operar sem UI.

| Comando | Faz |
|---|---|
| `php artisan admin:promote {nickname#tag\|email}` | Torna admin. Usado para criar o primeiro admin (fundador), que não tem convite. |
| `php artisan admin:demote {nickname#tag\|email}` | Remove admin. Recusa se for o último admin. |
| `php artisan invite:create {--prefix=} {--uses=1} {--days=30\|--never} {--by=}` | Gera código. Sem `--by`, usa o primeiro admin. Imprime o código. |
| `php artisan invite:revoke {code}` | Revoga. Não afeta quem já entrou. |
| `php artisan invite:list {--status=ativo\|esgotado\|expirado\|revogado}` | Tabela no terminal: código, criador, usos, expira, status. |

Todos chamam as mesmas Actions usadas pelos controllers (abaixo). Lógica nunca duplicada entre comando e HTTP.

## Actions / rotas

Middleware `auth` + `can:admin` (Gate `admin` → `$user->is_admin`) em todo o grupo `/admin`.

| Método | Rota | Action | Regras |
|---|---|---|---|
| GET | `/admin/convites` | — | Inertia page `Admin/Invites`. Lista com status derivado e usuários que usaram. |
| POST | `/admin/convites` | `CreateInvite` | `prefix`: `[A-Z0-9]{1,8}`, uppercase. `max_uses`: 1–10. `validity`: `7` \| `30` \| `never`. Sufixo gerado no servidor (ver abaixo). Retorna o código criado. |
| POST | `/admin/convites/{invite}/revogar` | `RevokeInvite` | Só se `status = ativo`. Grava `revoked_at`, `revoked_by`. |
| GET | `/admin/jogadores` | — | Inertia page `Admin/Players`. Busca `q` em `nickname`, `nickname#tag`, `email` (ILIKE). Filtro `admin`. |
| PATCH | `/admin/jogadores/{user}/cota` | `SetInviteQuota` | `quota`: 0–9. Ignorado/422 se alvo é admin. |
| PATCH | `/admin/jogadores/{user}/papel` | `SetAdmin` | `is_admin`: bool. Recusa (403) se alvo = usuário logado. Recusa (422) se for remover o último admin. Se a UI usa confirmação digitada, enviar também `confirm: "Nick#1234"` e validar no servidor (case-insensitive). |

**Sufixo do código:** 4 caracteres do alfabeto `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (sem `I O 0 1`, evita confusão ao ditar). Gerar com `random_int`, checar unicidade de `code`, tentar de novo em colisão. Formato final `PREFIXO-XXXX`.

**Prévia do código no front:** o protótipo sorteia o sufixo no cliente só para a animação de caça-níquel. Em produção, peça o sufixo ao servidor (`GET /admin/convites/sufixo`) ou mostre a animação e substitua pelo valor retornado no POST. O valor gravado sempre vem do servidor.

Jogadores comuns (não admin) geram convites pela própria conta, limitados por `invite_quota`. Fora do escopo deste painel.

---

## Telas

### Layout geral
- Fundo `#eff0f2`. Container `max-width: 1180px`, centralizado, padding lateral 28px.
- **Header** (padding 20/28, flex wrap, gap 16, space-between):
  - Esquerda: badge do logo (fundo `#f24d4d`, raio 12, padding 7/12/8, sombra `0 6px 14px rgba(242,77,77,.28)`), cápsula 20px + "512" (Inter 900, 20px, ls −0.03em, branco). Ao lado, "ADMIN" (11px / 800 / ls .18em / uppercase / `#767c85`).
  - Centro: **tabs em pílula** — trilho branco, padding 4, raio 999, sombra `0 2px 3px rgba(28,32,38,.05), 0 8px 20px rgba(28,32,38,.06)`. Botões 40px de altura, padding 0/18, 14px / 800. Ativo: fundo `#2b2f36`, texto branco. Inativo: transparente, `#565d66`. Contador ao lado (12px, opacity .7): convites ativos / total de jogadores.
  - Direita: nome (14/800) + "Admin" (12/600 `#767c85`), avatar 38px `#2b2f36` com inicial.
- **Main:** padding 12/28/80, coluna com gap 22.
- **Título da seção:** 34px / 900 / ls −0.03em / lh 1.05. Subtítulo 14px / 600 / `#6c737c`.

### Aba Convites
- Título "Convites" / "Cada código libera um cadastro. Revogar não afeta quem já entrou."
- **Botão primário** "Gerar convite" (vira "Fechar" quando aberto): 50px, padding 0/22/0/18, ícone pokébola 20px, sombra glow `0 10px 22px rgba(242,77,77,.42)`, active `scale(.97)`.
- **Card gerador** (abre com `popIn` .32s `cubic-bezier(.2,1.4,.4,1)`): branco, raio 28, padding 26, flex wrap gap 28.
  - **Esquerda (prévia):** painel `#1c1f26` com glow `radial-gradient(70% 45% at 50% 46%, rgba(242,77,77,.16), transparent)`, raio 20. "NOVO CÓDIGO" (11/800/ls .18em `#7d8794`). Código em **JetBrains Mono 800 / 30px**, um tile por caractere (min-width 26, altura 50, raio 8, gap 3). Prefixo branco; hífen `#7d8794`; sufixo: travado = fundo `rgba(242,77,77,.18)` texto `#ff8a8a`, girando = fundo `rgba(255,255,255,.06)` texto `#7d8794`.
  - **Animação do sufixo:** troca de caracteres a cada 45ms; trava 1 caractere a cada 4 ticks (≈180ms), da esquerda pra direita. Total ≈720ms. Botão "Sortear outro" (34px, fundo `rgba(255,255,255,.08)`, texto `#c3cad4`) refaz.
  - **Direita (campos):**
    - "Prefixo": input pílula 48px, fundo `#f4f5f6`, JetBrains Mono 800 16px, uppercase, aceita só `[A-Z0-9]`, máx 8. Foco: `inset 0 0 0 2px #f24d4d` + fundo branco. Default = nickname do admin.
    - "Usos": stepper pílula 48px (`#f4f5f6`), botões −/+ brancos 40px, valor 18/900. Faixa 1–10.
    - "Validade": segmented pílula 48px com 3 opções: "7 dias", "30 dias" (default), "Sem validade". Ativa = branco com sombra `0 1px 3px rgba(28,32,38,.1)`.
    - Rodapé: "Copiar" (secundário, borda `inset 0 0 0 2px #e4e6ea`; vira "Copiado") + "Criar convite" (primário, flex 1.4). Altura 52.
  - Ao criar: card fecha, filtro volta para "Todos", nova linha entra no topo com `rowIn` (.45s, de `translateY(-8px)`), toast "CÓDIGO criado".
- **Filtros:** chips 36px — Todos, Ativos, Esgotados, Expirados, Revogados, com contagem (opacity .6). Ativo `#2b2f36`/branco; inativo branco com sombra leve.
- **Tabela:** card branco raio 24, `overflow-x: auto`, `min-width: 750px` interno.
  - Colunas: `minmax(150px,1.4fr) minmax(120px,1.2fr) 96px 84px 100px 80px`, gap 14, padding 16/24 (compacta: 10/24).
  - Cabeçalho: 11px / 800 / ls .12em / uppercase / `#8a9099`. Colunas: Código, Usado por, Usos, Expira, Status, (ações).
  - **Código:** Mono 800 15px; abaixo "por {criador} · {dd mmm}" 12/600 `#8a9099`. Revogado = riscado.
  - **Usado por:** avatares 28px sobrepostos (−6px, anel branco 2px) + "Nick#1234" se 1 uso, ou nicks separados por vírgula. Vazio: "Ninguém ainda" `#a3a8b0`.
  - **Usos:** "X de Y" 13/800 + barra 84×6, trilho `#eef0f2`, preenchimento `#f24d4d` se ativo, `#c3c7cd` senão.
  - **Expira:** "dd mmm" ou "Nunca".
  - **Status (pill 26px com ponto):** Ativo `#e3f1ea`/`#2f7556` · Esgotado `#eceef1`/`#656b74` · Expirado `#f6e9df`/`#8f5735` · Revogado `#ffe6e6`/`#d63c3c`.
  - Linhas expiradas/revogadas com opacity .6. Hover da linha `#fafafb`.
  - **Ações:** copiar (36px, `#f4f5f6`; após clicar vira check verde `#e3f1ea`/`#2f7556` por 1.4s) e revogar (só se ativo; hover `#ffe6e6`/`#f24d4d`). Revogar é imediato, sem modal, com toast.
  - Vazio: "Nenhum convite nesse filtro."

### Aba Jogadores
- Título "Jogadores" / "Admins geram convites sem cota, revogam códigos e promovem outros jogadores."
- **Busca:** input pílula 50px branco, ícone lupa à esquerda (18px), placeholder "Buscar nickname ou email". Flex `0 1 300px`, min 220.
- **Filtros:** Todos, Admins (com contagem).
- **Tabela:** `min-width: 800px`. Colunas `minmax(190px,1.6fr) minmax(110px,1fr) 64px 72px 116px 134px`.
  - **Jogador:** avatar 40px colorido com inicial; admin ganha selo estrela 18px `#f24d4d` no canto inferior direito. "Nick" 15/900 + "#1234" 13/600 `#a3a8b0` + chip "você" (11/800, fundo `#eff0f2`) na própria linha. Email 12/600 `#8a9099`, ellipsis.
  - **Entrou com:** código Mono 13 + "convite de {nick}" (ou "Fundador").
  - **Desde:** "dd mmm". **Espécimes:** número 15/900.
  - **Cota de convites:** stepper pílula 36px (−/+ 30px) com faixa 0–9. Admin mostra "Sem limite".
  - **Papel:** botão 36px. Não admin: "Tornar admin" (`#2b2f36`, branco). Admin: "Remover admin" (branco, borda `#e4e6ea`). Próprio usuário: "Admin" desabilitado, opacity .5, title "Você não pode remover seu próprio acesso". Último admin também trava.
  - Vazio: "Ninguém encontrado."

### Modal de confirmação (papel)
- Overlay `rgba(28,31,38,.55)`, fade .2s. Clique fora fecha.
- Card branco max-width 420, raio 32, padding 30/28/26, gap 18, sombra `0 30px 80px rgba(28,31,38,.35)`, entra com `popIn`.
- Avatar 72px + selo estrela 28px (vermelho ao promover, `#a3a8b0` ao rebaixar).
- **Promover:** "Tornar {nick} admin?" / "Vai poder gerar convites sem cota, revogar códigos e promover ou rebaixar outros jogadores." / botão "Tornar admin".
- **Rebaixar:** "Remover admin de {nick}?" / "Volta a ser jogador comum, com cota de convites normal. A coleção não muda." / botão "Remover".
- **Confirmação digitada** (default): "Digite **Nick#1234** para confirmar" + input Mono centralizado. Botão fica `#f5b3b3`, sem sombra, `not-allowed` até bater (case-insensitive, trim). O `#tag` é obrigatório porque nicknames se repetem (ex.: dois "Lin").
- Botões: "Cancelar" (secundário) + ação (primário), grid 1fr 1fr, 52px.

### Toast
Fixo, centralizado, 28px do fundo. Pílula 46px `#2b2f36`, texto branco 14/800, ícone check em círculo 22px `#3f8f6b`. Some após 2.2s. Mensagens: "{código} criado", "{código} copiado", "{código} revogado", "{nick} agora é admin", "{nick} voltou a ser jogador".

---

## Estado (página)
- `tab`: `invites | users` (refletir na URL: `/admin/convites`, `/admin/jogadores`)
- Convites: `filter`, `gen` (card aberto), `prefix`, `suffix`, `uses`, `validity`, `copied` (id da linha com check), `fresh` (id recém-criado para animar)
- Jogadores: `q`, `ufilter`, `confirm` (id do alvo), `confirmText`
- `toast`
- Cota e papel: atualização otimista com rollback em erro do servidor.

## Variações no protótipo (props)
- `confirmMode`: "Digitar nickname" (recomendado) | "Simples"
- `density`: "Confortável" | "Compacta" (padding da linha)
- `startTab`: "Convites" | "Jogadores"

## Responsivo
Header e barras de título quebram linha (flex wrap). Tabelas rolam horizontalmente dentro do card abaixo de ~850px; o resto da página não rola na horizontal. Uso principal é desktop.

## Arquivos
- `512 Admin.dc.html`: protótipo. Lógica em `Component`: `status`, `roll` (animação do sufixo), `create`, `copy`, `applyRole`, `typed`.
