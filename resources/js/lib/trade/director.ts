import { animate, finished, prefersReducedMotion, wait } from '@/lib/motion';
import type { FxCanvas } from '@/lib/pack/fx';
import { CYAN, GOLD, TIER_META } from '@/lib/pack/meta';
import type { Sfx } from '@/lib/pack/sfx';
import type { Tier } from '@/lib/pack/types';

/**
 * Quanto a cerimônia capricha, pelo melhor espécime recebido: 0 comum,
 * 1 incomum, 2 raro, 3 lendário, 4 shiny (qualquer tier). Cada nível
 * soma ao anterior, no espírito da revelação do pack.
 */
export type CeremonyLevel = 0 | 1 | 2 | 3 | 4;

const TIER_LEVEL: Record<Tier, CeremonyLevel> = {
    comum: 0,
    incomum: 1,
    raro: 2,
    lendario: 3,
};

export function ceremonyLevel(best: {
    tier: Tier;
    shiny: boolean;
}): CeremonyLevel {
    return best.shiny ? 4 : TIER_LEVEL[best.tier];
}

/** Cor do brilho da cerimônia: a da raridade, ciano no shiny. */
export function ceremonyColor(best: { tier: Tier; shiny: boolean }): string {
    return best.shiny ? CYAN : TIER_META[best.tier].glow;
}

function vibrate(pattern: number | number[]): void {
    try {
        navigator.vibrate?.(pattern);
    } catch {
        // sem vibração
    }
}

function centerOf(el: Element | null): { x: number; y: number } {
    const r = el?.getBoundingClientRect();

    return r
        ? { x: r.left + r.width / 2, y: r.top + r.height / 2 }
        : { x: innerWidth / 2, y: innerHeight / 2 };
}

/**
 * Roteiro da cerimônia (WAAPI puro, como o pack): as cartas dadas sobem
 * e as recebidas descem, cruzando em arco, flash no meio, as recebidas
 * brilham e o círculo branco cresce revelando o resumo. Do raro pra cima
 * a melhor carta carrega antes de cruzar (lendário e shiny com faíscas
 * puxadas e tilintar), a chegada ganha halo, partículas, raios, tremida e
 * vibração, e o brilho continua em volta dela no resumo. `skipped()`
 * interrompe o roteiro quando o jogador toca pra pular. Com movimento
 * reduzido o `animate()` e o `wait()` já encurtam tudo.
 */
export async function playTradeCeremony(
    root: HTMLElement,
    sfx: Sfx | null,
    fx: FxCanvas | null,
    best: { tier: Tier; shiny: boolean },
    skipped: () => boolean,
): Promise<void> {
    const $ = (k: string) => root.querySelector<HTMLElement>(`[data-k="${k}"]`);
    const all = (k: string) => [
        ...root.querySelectorAll<HTMLElement>(`[data-k="${k}"]`),
    ];
    const level = ceremonyLevel(best);
    const col = ceremonyColor(best);
    const bestCard = $('received-best');

    all('given').forEach((card, i) =>
        animate(
            card,
            [
                { transform: 'scale(.6)', opacity: 0 },
                { transform: 'none', opacity: 1 },
            ],
            420,
            {
                delay: i * 70,
                easing: 'cubic-bezier(.2,1.4,.4,1)',
            },
        ),
    );
    all('received').forEach((card, i) =>
        animate(
            card,
            [
                { transform: 'scale(.6)', opacity: 0 },
                { transform: 'none', opacity: 1 },
            ],
            420,
            {
                delay: 120 + i * 70,
                easing: 'cubic-bezier(.2,1.4,.4,1)',
            },
        ),
    );
    await wait(700);

    if (skipped()) {
        return;
    }

    // Carga: a melhor carta pulsa na cor dela antes de cruzar. Lendário e
    // shiny seguram mais, com faíscas puxadas pra carta e um tilintar.
    if (level >= 2 && bestCard) {
        const charge = level >= 3 ? 1300 : 600;
        const glow = level >= 3 ? 26 : 14;
        sfx?.rise();
        animate(
            bestCard,
            [
                { filter: 'brightness(1) drop-shadow(0 0 0 transparent)' },
                {
                    filter: `brightness(1.25) drop-shadow(0 0 ${glow}px ${col})`,
                },
                { filter: 'brightness(1) drop-shadow(0 0 4px transparent)' },
            ],
            charge / (level >= 3 ? 2 : 1),
            { iterations: level >= 3 ? 2 : 1, easing: 'ease-in-out' },
        );

        if (level >= 3 && fx) {
            // Faíscas puxadas pra carta durante a carga (só visual).
            const until = performance.now() + charge;
            let pulls = 0;
            const pull = () => {
                if (skipped() || performance.now() >= until) {
                    return;
                }

                const c = centerOf(bestCard);
                for (let k = 0; k < 3; k++) {
                    fx.attract(c.x, c.y, [col, '#ffffff']);
                }

                if (pulls++ % 3 === 0) {
                    sfx?.glint();
                }

                window.setTimeout(pull, 140);
            };
            pull();
        }

        await wait(charge);
        fx?.dropAttracted();

        if (skipped()) {
            return;
        }
    }

    sfx?.whoosh(true);
    // Cada fileira vai até a outra: as dadas sobem até onde estavam as
    // recebidas e as recebidas descem até você.
    const top = $('row-received')?.getBoundingClientRect().top ?? 0;
    const bottom = $('row-given')?.getBoundingClientRect().top ?? 0;
    const travel = Math.max(bottom - top, root.clientHeight * 0.3);
    const moves = [
        ...all('given').map((card) =>
            animate(
                card,
                [
                    { transform: 'translate(0, 0) rotate(0deg) scale(1)' },
                    {
                        transform: `translate(${travel * 0.12}px, ${-travel / 2}px) rotate(-14deg) scale(1.08)`,
                        offset: 0.5,
                    },
                    {
                        transform: `translate(0, ${-travel}px) rotate(0deg) scale(1)`,
                    },
                ],
                760,
                { easing: 'cubic-bezier(.6,0,.3,1)', fill: 'forwards' },
            ),
        ),
        ...all('received').map((card) =>
            animate(
                card,
                [
                    { transform: 'translate(0, 0) rotate(0deg) scale(1)' },
                    {
                        transform: `translate(${-travel * 0.12}px, ${travel / 2}px) rotate(14deg) scale(1.08)`,
                        offset: 0.5,
                    },
                    {
                        transform: `translate(0, ${travel}px) rotate(0deg) scale(1)`,
                    },
                ],
                760,
                { easing: 'cubic-bezier(.6,0,.3,1)', fill: 'forwards' },
            ),
        ),
    ];
    await wait(380);

    if (skipped()) {
        return;
    }

    sfx?.burst();
    animate(
        $('flash'),
        [
            { opacity: 0, transform: 'scale(.4)' },
            { opacity: 1, transform: 'scale(1.6)', offset: 0.3 },
            { opacity: 0, transform: 'scale(2.2)' },
        ],
        520,
    );

    if (level >= 3) {
        animate(
            $('screen-flash'),
            [
                { opacity: level === 4 ? 1 : 0.75 },
                { opacity: level === 4 ? 1 : 0.75, offset: 0.2 },
                { opacity: 0 },
            ],
            level === 4 ? 520 : 380,
            { easing: 'ease-out' },
        );
    }

    await Promise.all(moves.map(finished));

    if (skipped()) {
        return;
    }

    // Chegada: o acorde e o brilho de sempre; o resto cresce com o nível.
    sfx?.chord(best.tier, best.shiny);
    all('received').forEach((card) =>
        animate(
            card,
            [
                { filter: 'brightness(1)' },
                { filter: 'brightness(1.6) drop-shadow(0 0 18px #fff)' },
                { filter: 'brightness(1)' },
            ],
            600,
        ),
    );
    const c = centerOf(bestCard);

    if (level >= 1) {
        fx?.emit(c.x, c.y, level >= 2 ? 60 : 24, {
            cols: [col, '#ffffff', col],
            sp: level >= 2 ? [3, 12] : [2, 7],
            r: [1.5, 4],
            g: 0.06,
            drag: 0.94,
            free: true,
            life: [0.6, 1.4],
        });
    }

    if (level >= 2) {
        sfx?.open();
        vibrate(level >= 3 ? [20, 30, 60] : 25);
        const halo = $('halo');

        if (halo) {
            halo.style.left = `${c.x}px`;
            halo.style.top = `${c.y}px`;
            animate(
                halo,
                [
                    {
                        opacity: 0,
                        transform: 'translate(-50%, -50%) scale(.3)',
                    },
                    {
                        opacity: 1,
                        transform: 'translate(-50%, -50%) scale(1)',
                        offset: 0.35,
                    },
                    {
                        opacity: 0,
                        transform: 'translate(-50%, -50%) scale(1.3)',
                    },
                ],
                level >= 3 ? 2200 : 1400,
                { easing: 'cubic-bezier(.2,.9,.2,1)' },
            );
        }

        fx?.emit(c.x, c.y, 36, {
            cols: [col, '#ffffff'],
            sp: [5, 11],
            r: [1.5, 3.5],
            g: 0,
            drag: 0.9,
            free: true,
            shape: 'spark',
            life: [0.4, 0.8],
        });
    }

    if (level >= 3) {
        const rays = $('rays');

        if (rays) {
            rays.style.left = `${c.x}px`;
            rays.style.top = `${c.y}px`;
            animate(
                rays,
                [
                    {
                        opacity: 0,
                        transform:
                            'translate(-50%, -50%) rotate(0deg) scale(.4)',
                    },
                    {
                        opacity: 0.9,
                        transform:
                            'translate(-50%, -50%) rotate(40deg) scale(1)',
                        offset: 0.3,
                    },
                    {
                        opacity: 0,
                        transform:
                            'translate(-50%, -50%) rotate(110deg) scale(1.15)',
                    },
                ],
                2400,
                { easing: 'ease-out' },
            );
        }

        if (!prefersReducedMotion()) {
            animate(
                $('stage'),
                [
                    { transform: 'none' },
                    { transform: 'translate(-6px, 3px)' },
                    { transform: 'translate(5px, -4px)' },
                    { transform: 'translate(-3px, 2px)' },
                    { transform: 'none' },
                ],
                360,
            );
        }

        // Chuva caindo do alto da tela, dourada (ou ciano e branca no shiny).
        const rain = level === 4 ? [CYAN, '#ffffff', GOLD] : [GOLD, '#fff3c4'];

        for (let k = 0; k < 5; k++) {
            fx?.emit((innerWidth / 6) * (k + 1), -10, 10, {
                cols: rain,
                ang: Math.PI / 2,
                spread: 0.9,
                sp: [2, 6],
                r: [1.5, 3.5],
                g: 0.1,
                drag: 0.985,
                free: true,
                life: [1.4, 2.4],
                jit: 40,
            });
        }
    }

    if (level === 4) {
        fx?.emit(c.x, c.y, 34, {
            cols: [CYAN, '#ffffff'],
            shape: 'star',
            sp: [2, 9],
            r: [4, 9],
            g: 0,
            drag: 0.93,
            free: true,
            life: [0.8, 1.6],
        });
        setTimeout(() => sfx?.glint(), 300);
        setTimeout(() => sfx?.glint(), 620);
    }

    if (fx && level >= 3 && bestCard) {
        fx.twinkle = { el: bestCard, col: level === 4 ? CYAN : GOLD };
    }

    await wait([450, 600, 900, 1500, 1700][level]);

    if (skipped()) {
        return;
    }

    sfx?.summary();
    const summary = $('summary');
    const reveal = 1050;

    // As cartas do resumo entram enquanto o círculo abre (escondidas até a
    // vez delas): se esperassem o círculo, apareceriam prontas e depois
    // sumiriam pra entrar de novo.
    all('summary-card').forEach((card, i) =>
        animate(
            card,
            [
                {
                    opacity: 0,
                    transform: 'translateY(60px) rotateX(-24deg) scale(.9)',
                },
                { opacity: 1, transform: 'none' },
            ],
            760,
            {
                delay: reveal * 0.35 + i * 90,
                easing: 'cubic-bezier(.2,1.3,.4,1)',
            },
        ),
    );

    if (summary) {
        summary.style.visibility = 'visible';
        await finished(
            animate(
                summary,
                [
                    { clipPath: 'circle(0% at 50% 55%)' },
                    { clipPath: 'circle(150% at 50% 55%)' },
                ],
                reveal,
                {
                    easing: 'cubic-bezier(.7,0,.2,1)',
                    fill: 'forwards',
                },
            ),
        );
    }

    settleSummary(root, fx, best);
}

/**
 * Estado final (também usado ao pular): o brilho passa pra melhor carta
 * do resumo, do lendário pra cima.
 */
export function settleSummary(
    root: HTMLElement,
    fx: FxCanvas | null,
    best: { tier: Tier; shiny: boolean },
): void {
    const level = ceremonyLevel(best);
    const card = root.querySelector('[data-k="summary-best"]');

    if (!fx) {
        return;
    }

    fx.dropAttracted();
    fx.twinkle =
        level >= 3 && card
            ? { el: card, col: level === 4 ? CYAN : GOLD }
            : null;
}
