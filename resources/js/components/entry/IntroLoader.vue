<script setup lang="ts">
import { ref } from 'vue';
import BrandLogo from '@/components/entry/BrandLogo.vue';
import { useDotField } from '@/composables/useDotField';
import { animate, createGeneration, spring, wait } from '@/lib/motion';

/**
 * Abertura (~3.6s): os 512 pontos acendem, espiralam pro centro, o ponto
 * vermelho estoura numa onda, a cápsula nasce, abre como boca, cospe o
 * "512", fecha com mola e pisca. Depois o logo voa até o card de login
 * (`flyTo`). Timings 1:1 com `runLoading`/`flight` do protótipo.
 */

const lock = ref<HTMLElement | null>(null);
const counterWrap = ref<HTMLElement | null>(null);
const counter = ref('000 / 512');
const isCounterComplete = ref(false);
const field = useDotField();
const generation = createGeneration();

function updateCounter(born: number) {
    const text = `${String(born).padStart(3, '0')} / 512`;

    if (text !== counter.value) {
        counter.value = text;
        isCounterComplete.value = born >= 512;
    }
}

function later(ms: number, callback: () => void, current: number) {
    window.setTimeout(() => {
        if (generation.isCurrent(current)) {
            callback();
        }
    }, ms);
}

async function play(): Promise<void> {
    const current = generation.next();
    const root = lock.value;

    if (!root) {
        return;
    }

    const part = (key: string) => root.querySelector(`[data-k="${key}"]`);
    const parts = (key: string) => [
        ...root.querySelectorAll(`[data-k="${key}"]`),
    ];
    const lockRect = root.getBoundingClientRect();
    const tokenRect = (part('token') as HTMLElement).getBoundingClientRect();
    const tokenCenter = tokenRect.left + tokenRect.width / 2 - lockRect.left;
    const slideFrom = lockRect.width / 2 - tokenCenter;
    const run = (
        element: Element | null,
        keyframes: Keyframe[],
        duration: number,
        delay: number,
        easing: string,
        fill: FillMode = 'both',
    ) => animate(element, keyframes, duration, { delay, easing, fill });

    field.grid(updateCounter);

    run(
        counterWrap.value,
        [
            { opacity: 0, transform: 'translateY(8px)' },
            { opacity: 1, transform: 'none' },
        ],
        400,
        100,
        'ease-out',
    );
    run(
        counterWrap.value,
        [{ opacity: 1 }, { opacity: 0 }],
        260,
        1650,
        'ease-in',
        'forwards',
    );

    const center = field.center();
    later(1520, () => field.swirlInto(center.x, center.y, 7), current);
    later(1820, () => field.fadeAllExceptSpecial(), current);
    later(1980, () => field.popSpecial(), current);

    run(root, [{ opacity: 0 }, { opacity: 1 }], 1, 1950, 'linear');
    run(
        part('slide'),
        [{ transform: `translateX(${slideFrom}px)` }, { transform: 'none' }],
        820,
        2300,
        spring(150, 19),
    );
    run(
        part('token'),
        [{ transform: 'scale(0) rotate(-30deg)' }, { transform: 'none' }],
        800,
        1950,
        spring(300, 13),
    );

    const top = part('top');
    const bottom = part('bot');
    run(top, [{ color: '#f24d4d' }, { color: '#ff5757' }], 220, 2320, 'linear');
    run(
        bottom,
        [{ background: '#f24d4d' }, { background: '#ffffff' }],
        220,
        2320,
        'linear',
    );
    run(
        top,
        [
            { transform: 'none' },
            { transform: 'translateY(-20px) rotate(-18deg)' },
        ],
        240,
        2240,
        'cubic-bezier(.2,.8,.3,1)',
    );
    run(
        bottom,
        [{ transform: 'none' }, { transform: 'translateY(12px) rotate(9deg)' }],
        240,
        2240,
        'cubic-bezier(.2,.8,.3,1)',
    );
    run(
        top,
        [
            { transform: 'translateY(-20px) rotate(-18deg)' },
            { transform: 'none' },
        ],
        620,
        2680,
        spring(380, 16),
        'forwards',
    );
    run(
        bottom,
        [{ transform: 'translateY(12px) rotate(9deg)' }, { transform: 'none' }],
        620,
        2680,
        spring(380, 16),
        'forwards',
    );

    const insetTop = tokenRect.top - lockRect.top - 3;
    const insetBottom = lockRect.bottom - tokenRect.bottom - 3;
    const insetLeft = tokenRect.left - lockRect.left - 3;
    const insetRight = lockRect.right - tokenRect.right - 3;
    run(
        part('field'),
        [
            {
                clipPath: `inset(${insetTop}px ${insetRight}px ${insetBottom}px ${insetLeft}px round 51px)`,
                opacity: 0,
            },
            { opacity: 1, offset: 0.04 },
            {
                clipPath: 'inset(-40px -40px -40px -40px round 60px)',
                opacity: 1,
            },
        ],
        820,
        2300,
        spring(150, 19),
        'backwards',
    );

    parts('d').forEach((digit, index) => {
        const rect = digit.getBoundingClientRect();
        const offset =
            tokenRect.left + tokenRect.width / 2 - (rect.left + rect.width / 2);
        run(
            digit,
            [
                {
                    transform: `translateX(${offset}px) scale(.15) rotate(-35deg)`,
                    opacity: 0,
                },
                { opacity: 1, offset: 0.12 },
                { transform: 'none', opacity: 1 },
            ],
            900,
            2360 + index * 80,
            spring(220, 13),
        );
    });

    run(
        part('squash'),
        [
            { transform: 'none' },
            { transform: 'scale(1.07,.9)', offset: 0.22 },
            { transform: 'scale(.97,1.04)', offset: 0.55 },
            { transform: 'none' },
        ],
        520,
        2700,
        'ease-out',
        'none',
    );
    run(
        part('hl'),
        [
            { transform: 'scaleY(1)' },
            { transform: 'scaleY(.08)', offset: 0.45 },
            { transform: 'scaleY(1)' },
        ],
        260,
        3150,
        'ease-in-out',
        'none',
    );

    await wait(3600);
}

/** O logo voa em arco até `target` (o logo do card de login). */
async function flyTo(target: Element, duration: number): Promise<void> {
    const root = lock.value;

    if (!root) {
        return;
    }

    const from = root.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);
    const scale = to.width / from.width;

    animate(
        root,
        [
            { transform: 'none' },
            {
                transform: `translate(${dx * 0.55}px, ${dy * 0.35}px) scale(${(1 + scale) / 2 + 0.06})`,
                offset: 0.45,
            },
            { transform: `translate(${dx}px, ${dy}px) scale(${scale})` },
        ],
        duration,
        { easing: 'cubic-bezier(.6,0,.25,1)', fill: 'forwards' },
    );

    await wait(duration + 20);
}

/** Sem logo pra onde voar (telas fora do login): amassa e some. */
async function vanish(): Promise<void> {
    animate(
        lock.value,
        [
            { transform: 'none', opacity: 1 },
            { transform: 'scale(1.06,.92)', opacity: 1, offset: 0.3 },
            { transform: 'translateY(-24px) scale(.6)', opacity: 0 },
        ],
        420,
        { easing: 'cubic-bezier(.5,0,.75,.35)', fill: 'forwards' },
    );

    await wait(440);
}

function cancel() {
    generation.invalidate();
}

defineExpose({ play, flyTo, vanish, cancel });
</script>

<template>
    <div class="pointer-events-none fixed inset-0 z-[8]" aria-hidden="true">
        <div class="flex h-full items-center justify-center">
            <div ref="lock" class="relative opacity-0">
                <div data-k="slide" class="relative">
                    <BrandLogo />
                </div>
            </div>
        </div>
        <div
            ref="counterWrap"
            class="fixed right-0 left-0 flex -translate-y-1/2 justify-center opacity-0"
            style="top: 50%"
        >
            <div
                class="bg-base rounded-full px-5 py-2.5 text-[15px] font-black tracking-[0.1em] tabular-nums shadow-[0_0_0_10px_var(--color-base),0_10px_30px_rgba(28,32,38,0.12)]"
                :class="isCounterComplete ? 'text-brand' : 'text-faint'"
            >
                {{ counter }}
            </div>
        </div>
    </div>
</template>
