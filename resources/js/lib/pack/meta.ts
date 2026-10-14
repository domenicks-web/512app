import type { PackSpecimen, Tier } from './types';

/**
 * Cores, rótulos e textos da abertura (constantes `TIER`, `CYAN`,
 * `natureCol` e afins do protótipo `512 Abertura`). Só apresentação: os
 * valores vêm prontos do servidor.
 */
export const CYAN = '#4DD9E8';
export const GOLD = '#FFC94D';
export const STATS = ['HP', 'Atk', 'Def', 'SpA', 'SpD', 'Spe'];
export const IV_MAX = 186;

export const TIER_META: Record<
    Tier,
    { label: string; plural: string; color: string; fg: string; glow: string }
> = {
    comum: {
        label: 'Comum',
        plural: 'comuns',
        color: '#463C39',
        fg: '#ffffff',
        glow: '#9aa3ad',
    },
    incomum: {
        label: 'Incomum',
        plural: 'incomuns',
        color: '#8F5735',
        fg: '#ffffff',
        glow: '#c27a4c',
    },
    raro: {
        label: 'Raro',
        plural: 'raros',
        color: '#E07C55',
        fg: '#2b2f36',
        glow: '#E07C55',
    },
    lendario: {
        label: 'Lendário',
        plural: 'lendários',
        color: GOLD,
        fg: '#2b2f36',
        glow: GOLD,
    },
};

export const pad = (value: number, length: number): string =>
    String(value).padStart(length, '0');

export function rgba(hex: string, alpha: number): string {
    const n = parseInt(hex.slice(1), 16);

    return `rgba(${n >> 16},${(n >> 8) & 255},${n & 255},${alpha})`;
}

export const natureColor = (index: number): string =>
    `oklch(0.74 0.14 ${Math.round(index * 14.4 + 20)})`;

/** Raro, lendário e shiny ganham cerimônia; o resto abre rápido. */
export const hasCeremony = (specimen: PackSpecimen): boolean =>
    specimen.isShiny ||
    specimen.tier === 'raro' ||
    specimen.tier === 'lendario';

export const glowOf = (specimen: PackSpecimen): string =>
    specimen.isShiny ? CYAN : TIER_META[specimen.tier].glow;

/** Barrinha embaixo do nome no slot. */
export const barColor = (specimen: PackSpecimen): string =>
    specimen.isShiny
        ? CYAN
        : specimen.tier === 'comum'
          ? '#7d8794'
          : TIER_META[specimen.tier].color;

export const isGoldMint = (specimen: PackSpecimen): boolean =>
    specimen.mint <= 10;

export const sizeLabel = (specimen: PackSpecimen): string =>
    specimen.sizeClass.toUpperCase();

export const sizeText = (specimen: PackSpecimen): string =>
    specimen.sizePercentile >= 50
        ? `maior que ${specimen.sizePercentile}% dos ${specimen.name}`
        : `menor que ${100 - specimen.sizePercentile}% dos ${specimen.name}`;

export const spriteOf = (specimen: PackSpecimen): string =>
    specimen.spriteAnimated ?? specimen.sprite;

export const panelBackground = (specimen: PackSpecimen): string => {
    const glow = glowOf(specimen);

    return `radial-gradient(70% 60% at 50% 55%, ${rgba(glow, specimen.tier === 'comum' && !specimen.isShiny ? 0.16 : 0.34)}, ${rgba(glow, 0)} 100%), #1c1f26`;
};

export const holoBackground = (specimen: PackSpecimen): string =>
    specimen.isShiny
        ? `linear-gradient(${specimen.holo.angle}deg, rgba(77,217,232,0) 18%, rgba(77,217,232,0.55) 36%, rgba(255,201,77,0.4) 50%, rgba(242,77,77,0.32) 62%, rgba(77,217,232,0) 82%)`
        : `linear-gradient(${specimen.holo.angle}deg, rgba(255,255,255,0) 30%, rgba(255,255,255,${(0.42 * specimen.holo.intensity).toFixed(2)}) 46%, rgba(255,255,255,0) 62%)`;

export const holoPosition = (specimen: PackSpecimen): string =>
    `${specimen.holo.offset}% 50%`;

/** Melhor do pack: shiny > lendário > raro > o garantido (último). */
export function bestOf(specimens: PackSpecimen[]): PackSpecimen {
    return (
        specimens.find((s) => s.isShiny) ??
        specimens.find((s) => s.tier === 'lendario') ??
        specimens.find((s) => s.tier === 'raro') ??
        specimens[specimens.length - 1]
    );
}

export function summaryTitle(specimens: PackSpecimen[]): string {
    const best = bestOf(specimens);

    if (best.isShiny) {
        return `${best.name} shiny no pack de hoje`;
    }

    return best.tier === 'lendario'
        ? `${best.name} lendário no pack de hoje`
        : `${best.name} no pack de hoje`;
}

export function summarySubtitle(specimens: PackSpecimen[]): string {
    const count = (tier: Tier) =>
        specimens.filter((s) => s.tier === tier).length;
    const shiny = specimens.filter((s) => s.isShiny).length;
    const part = (n: number, tier: Tier) =>
        n
            ? `${n} ${n === 1 ? TIER_META[tier].label.toLowerCase() : TIER_META[tier].plural}`
            : null;
    const parts = [
        `${count('comum')} ${count('comum') === 1 ? 'comum' : 'comuns'}`,
        part(count('incomum'), 'incomum'),
        part(count('raro'), 'raro'),
        part(count('lendario'), 'lendario'),
        shiny ? `${shiny} shiny` : null,
    ].filter(Boolean);

    return `${specimens.length} espécimes · ${parts.join(' · ')}`;
}

/** "maior que 67%" / "menor que 20%": o percentil curto, sem o nome. */
export const sizeShort = (specimen: PackSpecimen): string =>
    specimen.sizePercentile >= 50
        ? `maior que ${specimen.sizePercentile}%`
        : `menor que ${100 - specimen.sizePercentile}%`;

/** "06 nov", como o protótipo. */
export function dayMonth(date: Date): string {
    return date
        .toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
        .replace('.', '');
}

export function shortHash(hash: string): string {
    return `${hash.slice(0, 6)}…${hash.slice(-4)}`;
}

/** Escala inteira (1×, 2×, 3×), nunca fracionária: `k = floor(min(cap, maxW/w, maxH/h))`, mínimo 1. */
export function integerScale(
    naturalWidth: number,
    naturalHeight: number,
    cap: number,
    maxWidth: number,
    maxHeight: number,
): number {
    return Math.max(
        1,
        Math.floor(
            Math.min(cap, maxWidth / naturalWidth, maxHeight / naturalHeight),
        ),
    );
}
