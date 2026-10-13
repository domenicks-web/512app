import type { Ref } from 'vue';
import { useDotField } from '@/composables/useDotField';
import { animate, finished, spring, transformToRect } from '@/lib/motion';

/**
 * Transição entre passos com pills de progresso: o card se espreme até
 * virar a pill do passo atual, a pill "engole" e o card do próximo passo
 * nasce da pill seguinte, com o conteúdo entrando um a cada 70ms.
 */
export function usePillMorph(
    card: Ref<HTMLElement | null>,
    content: Ref<HTMLElement | null>,
    pills: Ref<HTMLElement | null>,
) {
    const field = useDotField();

    function pill(index: number): HTMLElement | null {
        return (
            (pills.value?.querySelectorAll<HTMLElement>('[data-pill]')[
                index
            ] as HTMLElement | undefined) ?? null
        );
    }

    function enterPills(delay = 160) {
        pills.value?.querySelectorAll('[data-pill]').forEach((element, index) =>
            animate(
                element,
                [{ transform: 'scaleX(0)' }, { transform: 'none' }],
                600,
                {
                    delay: delay + index * 70,
                    easing: spring(260, 15),
                },
            ),
        );
    }

    function morphFromPill(index: number, delay: number) {
        const target = pill(index);

        if (!card.value || !target) {
            return;
        }

        card.value.getAnimations().forEach((animation) => animation.cancel());
        content.value
            ?.getAnimations()
            .forEach((animation) => animation.cancel());

        animate(
            card.value,
            [
                {
                    transform: transformToRect(card.value, target),
                    borderRadius: '999px',
                    opacity: 0,
                },
                { opacity: 1, offset: 0.08 },
                { transform: 'none', borderRadius: '28px', opacity: 1 },
            ],
            780,
            { delay, easing: spring(190, 16) },
        );

        content.value?.querySelectorAll('[data-enter]').forEach((element, k) =>
            animate(
                element,
                [
                    { opacity: 0, transform: 'translateY(12px) scale(.98)' },
                    { opacity: 1, transform: 'none' },
                ],
                560,
                { delay: delay + 240 + k * 70, easing: spring(220, 18) },
            ),
        );

        const rect = target.getBoundingClientRect();
        field.ripple(
            rect.left + rect.width / 2,
            rect.top + rect.height / 2,
            0.9,
        );
    }

    async function collapseToPill(index: number): Promise<void> {
        const target = pill(index);

        if (!card.value || !target) {
            return;
        }

        const transform = transformToRect(card.value, target);
        animate(content.value, [{ opacity: 1 }, { opacity: 0 }], 140, {
            fill: 'forwards',
        });

        await finished(
            animate(
                card.value,
                [
                    { transform: 'none', borderRadius: '28px', opacity: 1 },
                    { transform: 'scale(1.03,.96)', offset: 0.2 },
                    {
                        transform,
                        borderRadius: '999px',
                        opacity: 1,
                        offset: 0.9,
                    },
                    { transform, borderRadius: '999px', opacity: 0 },
                ],
                480,
                {
                    delay: 80,
                    easing: 'cubic-bezier(.5,0,.75,.35)',
                    fill: 'forwards',
                },
            ),
        );
    }

    function gulp(index: number) {
        animate(
            pill(index),
            [
                { transform: 'none' },
                { transform: 'scale(1.3,2.1)', offset: 0.3 },
                { transform: 'none' },
            ],
            520,
            { easing: 'ease-out', fill: 'none' },
        );
    }

    /** Fecha o passo atual na pill dele e abre o próximo a partir da seguinte. */
    async function goStep(
        from: number,
        to: number,
        applyStep: () => Promise<void> | void,
    ) {
        await collapseToPill(from);
        gulp(from);
        await applyStep();
        morphFromPill(to, 40);
    }

    return { enterPills, morphFromPill, collapseToPill, gulp, goStep };
}
