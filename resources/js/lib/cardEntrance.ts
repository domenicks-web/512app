import { animate, spring } from '@/lib/motion';

/**
 * Entrada dos cards de autenticação (login, esqueci a senha, senha nova):
 * o card se abre a partir do retângulo do logo e os filhos com
 * `data-enter` sobem a cada 70ms. `popLogo` quando não houve abertura pra
 * trazer o logo voando: aí ele só dá um pop.
 */
export function enterCard(
    card: HTMLElement | null,
    logo: HTMLElement | null,
    delay: number,
    popLogo: boolean,
): void {
    if (!card || !logo) {
        return;
    }

    const cardRect = card.getBoundingClientRect();
    const logoRect = logo.getBoundingClientRect();
    const inset = `inset(${logoRect.top - cardRect.top}px ${cardRect.right - logoRect.right}px ${cardRect.bottom - logoRect.bottom}px ${logoRect.left - cardRect.left}px round 20px)`;

    animate(
        card,
        [
            { clipPath: inset, opacity: 0 },
            { opacity: 1, offset: 0.06 },
            { clipPath: 'inset(0px 0px 0px 0px round 28px)', opacity: 1 },
        ],
        780,
        { delay, easing: spring(170, 19) },
    );

    if (popLogo) {
        animate(
            logo,
            [{ transform: 'scale(0) rotate(-20deg)' }, { transform: 'none' }],
            700,
            {
                delay,
                easing: spring(280, 14),
            },
        );
    } else {
        animate(logo, [{ opacity: 0 }, { opacity: 1 }], 1, {
            delay: delay + 30,
        });
        animate(
            logo,
            [
                { transform: 'none' },
                { transform: 'scale(1.08,.9)', offset: 0.25 },
                { transform: 'scale(.97,1.03)', offset: 0.6 },
                { transform: 'none' },
            ],
            480,
            { delay: delay + 40, fill: 'none' },
        );
    }

    card.querySelectorAll('[data-enter]').forEach((element, index) =>
        animate(
            element,
            [
                { opacity: 0, transform: 'translateY(16px)' },
                { opacity: 1, transform: 'none' },
            ],
            700,
            {
                delay: delay + 200 + index * 70,
                easing: spring(200, 17),
            },
        ),
    );
}
