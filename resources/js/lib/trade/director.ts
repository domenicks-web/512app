import { animate, finished, wait } from '@/lib/motion';
import type { Sfx } from '@/lib/pack/sfx';
import type { Tier } from '@/lib/pack/types';

/**
 * Roteiro da cerimônia (WAAPI puro, como o pack): as cartas dadas sobem
 * e as recebidas descem, cruzando em arco com um giro de lado, flash no meio, as
 * recebidas brilham e o círculo branco cresce revelando o resumo. Com
 * movimento reduzido o `animate()` já encurta tudo.
 */
export async function playTradeCeremony(
    root: HTMLElement,
    sfx: Sfx | null,
    best: { tier: Tier; shiny: boolean },
): Promise<void> {
    const $ = (k: string) => root.querySelector<HTMLElement>(`[data-k="${k}"]`);
    const all = (k: string) => [
        ...root.querySelectorAll<HTMLElement>(`[data-k="${k}"]`),
    ];

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
    await Promise.all(moves.map(finished));

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
    await wait(450);

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
}
