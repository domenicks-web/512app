import { useDotField } from '@/composables/useDotField';
import { animate, finished, spring, wait } from '@/lib/motion';

/**
 * Envio "captura" do login e do cadastro: o botão encolhe até virar um
 * círculo, nasce uma pokébola dentro dele que balança 3 vezes (−24°, +22°,
 * −16°) enquanto o servidor responde. Só depois da resposta o resultado
 * aparece: se deu certo a pokébola infla e o card amassa e sobe; se deu
 * erro, tudo volta ao lugar. O cliente nunca decide nada — só espera.
 */
export function useCaptureSubmit() {
    const field = useDotField();

    async function capture(
        button: HTMLElement,
        card: HTMLElement,
        request: () => Promise<boolean>,
    ): Promise<boolean> {
        const label = button.querySelector('[data-k="blabel"]');
        const token = button.querySelector('[data-k="btok"]');
        const startedAt = performance.now();

        (document.activeElement as HTMLElement | null)?.blur?.();

        const running = [
            animate(
                button,
                [{ width: `${button.offsetWidth}px` }, { width: '54px' }],
                520,
                {
                    easing: spring(200, 20),
                    fill: 'forwards',
                },
            ),
            animate(
                label,
                [{ opacity: 1 }, { opacity: 0, transform: 'scale(.7)' }],
                160,
                {
                    fill: 'forwards',
                },
            ),
            animate(
                token,
                [
                    { transform: 'scale(0) rotate(-60deg)', opacity: 1 },
                    { transform: 'none', opacity: 1 },
                ],
                600,
                {
                    delay: 220,
                    easing: spring(300, 13),
                    fill: 'both',
                },
            ),
            animate(
                token,
                [
                    { transform: 'none' },
                    { transform: 'none', offset: 0.1 },
                    { transform: 'rotate(-24deg)', offset: 0.18 },
                    { transform: 'rotate(0)', offset: 0.28 },
                    { transform: 'rotate(0)', offset: 0.42 },
                    { transform: 'rotate(22deg)', offset: 0.5 },
                    { transform: 'rotate(0)', offset: 0.6 },
                    { transform: 'rotate(0)', offset: 0.74 },
                    { transform: 'rotate(-16deg)', offset: 0.82 },
                    { transform: 'rotate(0)', offset: 0.9 },
                    { transform: 'none' },
                ],
                1500,
                { delay: 780, easing: 'ease-in-out', fill: 'none' },
            ),
        ];

        const succeeded = await request();

        if (!succeeded) {
            running.forEach((animation) => animation?.cancel());

            return false;
        }

        await wait(Math.max(0, 2320 - (performance.now() - startedAt)));

        const rect = button.getBoundingClientRect();
        animate(
            token,
            [
                { transform: 'none', opacity: 1 },
                { transform: 'scale(1.7)', opacity: 1, offset: 0.4 },
                { transform: 'scale(0)', opacity: 0 },
            ],
            360,
            {
                easing: 'ease-out',
                fill: 'forwards',
            },
        );
        animate(
            button,
            [
                { boxShadow: '0 0 0 0 rgba(242,77,77,.5)' },
                { boxShadow: '0 0 0 26px rgba(242,77,77,0)' },
            ],
            500,
            { fill: 'none' },
        );
        field.ripple(
            rect.left + rect.width / 2,
            rect.top + rect.height / 2,
            1.6,
        );

        await wait(260);
        await finished(
            animate(
                card,
                [
                    { transform: 'none' },
                    { transform: 'scale(1.025,.96)', offset: 0.22 },
                    {
                        transform: 'translateY(-110px) scale(.92,.06)',
                        opacity: 0,
                    },
                ],
                460,
                { easing: 'cubic-bezier(.5,0,.75,.35)', fill: 'forwards' },
            ),
        );

        return true;
    }

    return { capture };
}
