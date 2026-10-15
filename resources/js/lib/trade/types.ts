import type { Tier } from '@/lib/pack/types';

/** Formatos que o servidor manda pras telas de troca (TradeSpecimenResource e afins). */
export type TradeSpecimen = {
    specimenId: number;
    speciesId: number;
    name: string;
    rarityTier: Tier;
    isShiny: boolean;
    spritePath: string;
    mintNumber: number;
    ivTotal: number;
};

export type TradeTrainer = {
    id: number;
    nickname: string;
    avatarSpritePath: string | null;
};

export type TradeState =
    | 'open'
    | 'expired'
    | 'cancelled'
    | 'accepted'
    | 'declined';

export type TradeOffer = {
    id: number;
    direction: 'received' | 'sent';
    state: TradeState;
    cancelReason: 'by_proposer' | 'unavailable' | null;
    other: TradeTrainer;
    message: string | null;
    isCounter: boolean;
    /** Desfecho de uma enviada que quem propôs ainda não viu. */
    isNew: boolean;
    expiresAt: string;
    createdAgo: string;
    give: TradeSpecimen[];
    receive: TradeSpecimen[];
};

export type TradeCeremonyData = {
    tradeId: number;
    other: TradeTrainer;
    received: TradeSpecimen[];
    given: TradeSpecimen[];
    newSpeciesIds: number[];
    markSeen: boolean;
};

export type BuilderSpecimen = TradeSpecimen & { duplicate: boolean };
