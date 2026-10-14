import { CYAN, GOLD, rgba, TIER_META } from './meta';
import type { Tier } from './types';

/**
 * A "força" visual de cada raridade, compartilhada pela carta da coleção e
 * pela ficha clara: moldura metálica, fundo com brilho e padrão, reflexo
 * que corre (só raro, lendário e shiny) e as gemas de raridade. O `card`
 * é o tratamento extra só da carta da coleção.
 */
type RarityLook = {
    /** Moldura de 2–3px em gradiente (prata, bronze, cobre, ouro, holo). */
    frame: string;
    /** Fundo da área do sprite (tema claro). */
    panel: string;
    /** Sombra externa: discreta nas comuns, brilho colorido nas raras. */
    shadow: string;
    /** Cor do texto/rótulo da raridade, legível no branco. */
    ink: string;
    /** Fundo do selo da raridade. */
    badge: string;
    badgeInk: string;
    gems: number;
    sheen: boolean;
    label: string;
    /** Só a carta da coleção: o tratamento de carta real de cada tier. */
    card: CardSkin;
};

/**
 * `foil`: nenhum (comum, incomum chapada), holo de estrelas (raro), ouro
 * escovado na carta inteira (lendário) ou arco-íris na carta escura
 * "full art" (shiny). `tilt` liga a inclinação com o mouse no desktop.
 */
export type CardSkin = {
    foil: 'none' | 'holo' | 'gold' | 'rainbow';
    /** Espessura da moldura, em px: cresce com a raridade. */
    frameWidth: number;
    /** Fundo da área do sprite, quando troca o `panel` (shiny escuro). */
    panel: string | null;
    /** Fundo da área de baixo (nome, selo, mint). */
    body: string;
    nameInk: string;
    mutedInk: string;
    tilt: boolean;
};

const LIGHT_BODY = {
    body: '#ffffff',
    nameInk: '#2b2f36',
    mutedInk: '#8a9099',
};

const DOTS = (color: string) =>
    `radial-gradient(${color} 1px, transparent 1.6px) 0 0 / 9px 9px`;

export function rarityLook(tier: Tier, shiny: boolean): RarityLook {
    if (shiny) {
        return {
            frame: `linear-gradient(135deg, ${CYAN}, #b6f3ff 22%, ${GOLD} 45%, #ff8a8a 62%, #c7a4ff 80%, ${CYAN})`,
            panel: `${DOTS(rgba(CYAN, 0.22))}, radial-gradient(75% 70% at 50% 45%, ${rgba(CYAN, 0.42)}, ${rgba(CYAN, 0)} 100%), linear-gradient(160deg, #f2fdff, #fff7e6)`,
            shadow: `0 10px 26px ${rgba(CYAN, 0.38)}, 0 2px 6px rgba(28,32,38,0.08)`,
            ink: '#0f8f9c',
            badge: CYAN,
            badgeInk: '#10343a',
            gems: 4,
            sheen: true,
            label: 'Shiny',
            card: {
                foil: 'rainbow',
                frameWidth: 3.5,
                panel: 'radial-gradient(70% 65% at 50% 50%, rgba(77,217,232,0.38), rgba(77,217,232,0) 100%), linear-gradient(160deg, #222a40, #12151e)',
                body: 'linear-gradient(180deg, #1b1e27, #12141b)',
                nameInk: '#ffffff',
                mutedInk: '#8f97a6',
                tilt: true,
            },
        };
    }

    switch (tier) {
        case 'lendario':
            return {
                frame: `linear-gradient(135deg, #fff3c4, ${GOLD} 25%, #b8860b 50%, #ffe08a 72%, ${GOLD})`,
                panel: `repeating-conic-gradient(from 0deg at 50% 46%, ${rgba(GOLD, 0.22)} 0deg 6deg, ${rgba(GOLD, 0)} 6deg 18deg), radial-gradient(70% 65% at 50% 46%, ${rgba(GOLD, 0.55)}, ${rgba(GOLD, 0)} 100%), linear-gradient(160deg, #fffaf0, #fff1d1)`,
                shadow: `0 12px 30px ${rgba(GOLD, 0.5)}, 0 2px 6px rgba(28,32,38,0.08)`,
                ink: '#a87700',
                badge: GOLD,
                badgeInk: '#3b2a00',
                gems: 4,
                sheen: true,
                label: TIER_META.lendario.label,
                card: {
                    foil: 'gold',
                    frameWidth: 3.5,
                    panel: null,
                    body: `repeating-linear-gradient(100deg, rgba(255,255,255,0.18) 0 2px, rgba(255,255,255,0) 2px 5px), linear-gradient(160deg, #ffe9a8, ${GOLD} 45%, #e0a92e)`,
                    nameInk: '#3b2a00',
                    mutedInk: '#7a5a12',
                    tilt: true,
                },
            };
        case 'raro':
            return {
                frame: 'linear-gradient(135deg, #ffc9ad, #E07C55 35%, #a8441f 60%, #ffb38f)',
                panel: `${DOTS(rgba('#E07C55', 0.2))}, radial-gradient(72% 66% at 50% 46%, ${rgba('#E07C55', 0.4)}, ${rgba('#E07C55', 0)} 100%), linear-gradient(160deg, #fff7f2, #ffe9de)`,
                shadow: `0 10px 26px ${rgba('#E07C55', 0.38)}, 0 2px 6px rgba(28,32,38,0.08)`,
                ink: '#c4562d',
                badge: '#E07C55',
                badgeInk: '#ffffff',
                gems: 3,
                sheen: true,
                label: TIER_META.raro.label,
                card: {
                    foil: 'holo',
                    frameWidth: 3,
                    panel: null,
                    ...LIGHT_BODY,
                    tilt: true,
                },
            };
        case 'incomum':
            return {
                frame: 'linear-gradient(135deg, #e3b48f, #8F5735 45%, #c98a5c)',
                panel: `${DOTS(rgba('#8F5735', 0.12))}, radial-gradient(70% 64% at 50% 46%, ${rgba('#c27a4c', 0.28)}, ${rgba('#c27a4c', 0)} 100%), linear-gradient(160deg, #fdf8f4, #f6ece4)`,
                shadow: '0 8px 20px rgba(143,87,53,0.18), 0 2px 5px rgba(28,32,38,0.06)',
                ink: '#8F5735',
                badge: '#8F5735',
                badgeInk: '#ffffff',
                gems: 2,
                sheen: false,
                label: TIER_META.incomum.label,
                card: {
                    foil: 'none',
                    panel: null,
                    frameWidth: 2.5,
                    ...LIGHT_BODY,
                    tilt: false,
                },
            };
        default:
            return {
                frame: 'linear-gradient(135deg, #e9ebee, #b9bec6 50%, #e3e6ea)',
                panel: `${DOTS('rgba(43,47,54,0.07)')}, radial-gradient(70% 64% at 50% 46%, rgba(154,163,173,0.22), rgba(154,163,173,0) 100%), linear-gradient(160deg, #fbfbfc, #f1f2f4)`,
                shadow: '0 6px 16px rgba(28,32,38,0.08), 0 1px 3px rgba(28,32,38,0.06)',
                ink: '#767c85',
                badge: '#c9cdd3',
                badgeInk: '#3c424a',
                gems: 1,
                sheen: false,
                label: TIER_META.comum.label,
                card: {
                    foil: 'none',
                    panel: null,
                    frameWidth: 1.5,
                    ...LIGHT_BODY,
                    tilt: false,
                },
            };
    }
}
