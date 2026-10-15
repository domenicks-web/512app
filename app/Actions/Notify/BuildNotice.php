<?php

declare(strict_types=1);

namespace App\Actions\Notify;

use App\Actions\Data\Notice;
use App\Enums\TradeItemSide;
use App\Models\Friendship;
use App\Models\Specimen;
use App\Models\Trade;
use App\Models\TradeItem;
use Illuminate\Support\Collection;

/**
 * Monta o aviso de cada acontecimento, do ponto de vista de quem recebe.
 * Todo texto da bolha nasce aqui.
 */
class BuildNotice
{
    private const TRADE_PROPS = ['tradeAlerts', 'pendingTrades', 'received', 'sent', 'done', 'newCounts'];

    private const FRIEND_PROPS = ['pendingFriendRequests', 'tradeAlerts', 'friends', 'incoming', 'outgoing'];

    private const TIER_ORDER = ['comum', 'incomum', 'raro', 'lendario'];

    /** Pra quem recebe a proposta: o melhor que vem pra ele em destaque. */
    public function tradeReceived(Trade $trade): Notice
    {
        $from = $trade->fromUser;
        $offered = $this->side($trade, TradeItemSide::Offered);
        $best = $this->best($offered);

        return new Notice(
            kind: 'trade_received',
            dock: 'trades',
            title: $trade->counter_of_id !== null
                ? "{$from->nickname} fez uma contraproposta"
                : "{$from->nickname} quer trocar",
            body: $this->names($offered).' ⇄ '.$this->names($this->side($trade, TradeItemSide::Requested)),
            spritePath: $this->sprite($best),
            tier: $best?->species->rarity_tier->value,
            isShiny: (bool) $best?->is_shiny,
            href: route('trades.index', ['aba' => 'recebidas'], false),
            reload: self::TRADE_PROPS,
            actorId: $from->id,
        );
    }

    /** Pra quem propôs: o que ele ganhou. A cerimônia toca ao abrir Trocas. */
    public function tradeAccepted(Trade $trade): Notice
    {
        $to = $trade->toUser;
        $requested = $this->side($trade, TradeItemSide::Requested);
        $best = $this->best($requested);

        return new Notice(
            kind: 'trade_accepted',
            dock: 'trades',
            title: "{$to->nickname} aceitou sua troca",
            body: 'Você recebeu '.$this->names($requested),
            spritePath: $this->sprite($best),
            tier: $best?->species->rarity_tier->value,
            isShiny: (bool) $best?->is_shiny,
            href: route('trades.index', [], false),
            reload: self::TRADE_PROPS,
            actorId: $to->id,
        );
    }

    public function tradeDeclined(Trade $trade): Notice
    {
        $to = $trade->toUser;

        return new Notice(
            kind: 'trade_declined',
            dock: 'trades',
            title: "{$to->nickname} recusou sua troca",
            body: null,
            spritePath: $to->avatarSpritePath(),
            tier: null,
            isShiny: false,
            href: route('trades.index', ['aba' => 'concluidas'], false),
            reload: self::TRADE_PROPS,
            actorId: $to->id,
        );
    }

    public function tradeFallen(Trade $trade): Notice
    {
        return new Notice(
            kind: 'trade_fallen',
            dock: 'trades',
            title: "Sua troca com {$trade->toUser->nickname} caiu",
            body: 'Um espécime foi trocado em outra',
            spritePath: $trade->toUser->avatarSpritePath(),
            tier: null,
            isShiny: false,
            href: route('trades.index', ['aba' => 'enviadas'], false),
            reload: self::TRADE_PROPS,
            actorId: null,
        );
    }

    public function friendRequest(Friendship $friendship): Notice
    {
        $requester = $friendship->requester;

        return new Notice(
            kind: 'friend_request',
            dock: 'friends',
            title: "{$requester->nickname} quer ser seu amigo",
            body: null,
            spritePath: $requester->avatarSpritePath(),
            tier: null,
            isShiny: false,
            href: route('friends.index', [], false),
            reload: self::FRIEND_PROPS,
            actorId: $requester->id,
        );
    }

    public function friendAccepted(Friendship $friendship): Notice
    {
        $addressee = $friendship->addressee;

        return new Notice(
            kind: 'friend_accepted',
            dock: 'friends',
            title: "{$addressee->nickname} aceitou seu pedido",
            body: 'Agora vocês são amigos',
            spritePath: $addressee->avatarSpritePath(),
            tier: null,
            isShiny: false,
            href: route('trainer.show', ['user' => $addressee->id], false),
            reload: self::FRIEND_PROPS,
            actorId: $addressee->id,
        );
    }

    /** Pro grupo: alguém tirou um shiny ou um tier do feed. */
    public function feedHighlight(Specimen $specimen): Notice
    {
        $opener = $specimen->opening->user;
        $name = $specimen->species->name.($specimen->is_shiny ? ' shiny' : '');

        return new Notice(
            kind: 'feed_highlight',
            dock: 'home',
            title: "{$opener->nickname} tirou {$name}!",
            body: $specimen->species->rarity_tier->label(),
            spritePath: $this->sprite($specimen),
            tier: $specimen->species->rarity_tier->value,
            isShiny: $specimen->is_shiny,
            href: route('feed.show', [], false),
            reload: ['feedHighlight'],
            actorId: $opener->id,
        );
    }

    /**
     * @return Collection<int, Specimen>
     */
    private function side(Trade $trade, TradeItemSide $side): Collection
    {
        return $trade->items()->with('specimen.species')->get()
            ->where('side', $side)
            ->map(fn (TradeItem $item): Specimen => $item->specimen)
            ->values();
    }

    /**
     * @param  Collection<int, Specimen>  $specimens
     */
    private function best(Collection $specimens): ?Specimen
    {
        return $specimens->sortByDesc(fn (Specimen $s): int => (int) $s->is_shiny * 10
            + (int) array_search($s->species->rarity_tier->value, self::TIER_ORDER, true))->first();
    }

    /**
     * @param  Collection<int, Specimen>  $specimens
     */
    private function names(Collection $specimens): string
    {
        return $specimens->map(fn (Specimen $s): string => $s->species->name)->join(', ');
    }

    private function sprite(?Specimen $specimen): ?string
    {
        if ($specimen === null) {
            return null;
        }

        return $specimen->is_shiny ? $specimen->species->sprite_shiny_path : $specimen->species->sprite_path;
    }
}
