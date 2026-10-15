import type { Tier } from '@/lib/pack/types';

/** Aviso em tempo real, igual ao `Notice::toArray()` do servidor. */
export type Notice = {
    kind:
        | 'trade_received'
        | 'trade_accepted'
        | 'trade_declined'
        | 'trade_fallen'
        | 'friend_request'
        | 'friend_accepted'
        | 'feed_highlight';
    dock: 'trades' | 'friends' | 'home';
    title: string;
    body: string | null;
    spritePath: string | null;
    tier: Tier | null;
    isShiny: boolean;
    href: string;
    reload: string[];
    actorId: number | null;
};
