<?php

declare(strict_types=1);

namespace App\Actions\Data;

/**
 * Um aviso em tempo real, pronto pra bolha do dock: o front só anima.
 * `dock` diz de qual ícone a bolha sai; `reload`, as props Inertia que a
 * tela aberta recarrega; `actorId`, quem causou (o front ignora o próprio
 * aviso do grupo).
 */
final readonly class Notice
{
    /**
     * @param  'trade_received'|'trade_accepted'|'trade_declined'|'trade_fallen'|'friend_request'|'friend_accepted'|'feed_highlight'  $kind
     * @param  'trades'|'friends'|'home'  $dock
     * @param  list<string>  $reload
     */
    public function __construct(
        public string $kind,
        public string $dock,
        public string $title,
        public ?string $body,
        public ?string $spritePath,
        public ?string $tier,
        public bool $isShiny,
        public string $href,
        public array $reload,
        public ?int $actorId,
    ) {}

    /**
     * @return array{kind: string, dock: string, title: string, body: ?string, spritePath: ?string, tier: ?string, isShiny: bool, href: string, reload: list<string>, actorId: ?int}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'dock' => $this->dock,
            'title' => $this->title,
            'body' => $this->body,
            'spritePath' => $this->spritePath,
            'tier' => $this->tier,
            'isShiny' => $this->isShiny,
            'href' => $this->href,
            'reload' => $this->reload,
            'actorId' => $this->actorId,
        ];
    }
}
