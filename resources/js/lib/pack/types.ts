/** Formato que o servidor manda (PackSpecimenResource e BuildPackScreen). */
export type Tier = 'comum' | 'incomum' | 'raro' | 'lendario';

export type PackSpecimen = {
    id: number;
    slot: number;
    speciesId: number;
    dex: string;
    name: string;
    tier: Tier;
    isShiny: boolean;
    mint: number;
    sprite: string;
    spriteAnimated: string | null;
    sizeRoll: number;
    sizeClass: string;
    sizePercentile: number;
    heightM: number;
    nature: string;
    /** Efeito nos jogos ("+Def −SpA" ou "Neutra"), só curiosidade. */
    natureEffect: string;
    natureIndex: number;
    ivs: number[];
    ivTotal: number;
    seed: string;
    hash: string;
    caughtAt: string;
    holo: { angle: number; offset: number; intensity: number };
};

export type Receipt = {
    serverSeed: string;
    serverSeedHash: string;
    clientSeed: string;
    nonce: number;
};

export type PackOpening = {
    id: number;
    createdAt: string;
    isComplete: boolean;
    slots: { slot: number; specimen: PackSpecimen | null }[];
    receipt: Receipt | null;
};
