<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { animate, shake } from '@/lib/motion';

/**
 * Grade 5×3 de parceiros com os sprites reais. Sprites não têm olhos, então
 * (adaptação do handoff) os não escolhidos "viram" com `scaleX(-1)` na
 * direção do cursor ou do escolhido, inclinando até ±7°; um sprite
 * aleatório dá um micro-pulo a cada ~900ms no lugar de piscar.
 */
export type PartnerOption = {
    id: number;
    name: string;
    sprite_path: string;
};

const props = defineProps<{
    options: PartnerOption[];
}>();

const selected = defineModel<number | null>({ required: true });

const emit = defineEmits<{
    picked: [index: number, sprite: HTMLImageElement];
}>();

const COLUMNS = 5;
const TILE = 64;
const GAP = 14;
const STEP = TILE + GAP;

const grid = ref<HTMLElement | null>(null);
const look = ref<{ x: number; y: number } | null>(null);
let idleTimer = 0;

const selectedIndex = computed(() =>
    props.options.findIndex((option) => option.id === selected.value),
);

const centerX = (i: number) => (i % COLUMNS) * STEP + TILE / 2;
const centerY = (i: number) => Math.floor(i / COLUMNS) * STEP + TILE / 2;

const tiles = computed(() => {
    let lookX: number | null = null;
    let lookY: number | null = null;

    if (look.value) {
        lookX = look.value.x;
        lookY = look.value.y;
    } else if (selectedIndex.value >= 0) {
        lookX = centerX(selectedIndex.value);
        lookY = centerY(selectedIndex.value);
    }

    return props.options.map((option, i) => {
        const isSelected = i === selectedIndex.value;
        let tileTransform = 'none';
        let facesRight = false;

        if (isSelected) {
            tileTransform = 'scale(1.12)';
        } else if (lookX !== null && lookY !== null) {
            const vx = lookX - centerX(i);
            const vy = lookY - centerY(i);
            const length = Math.hypot(vx, vy) || 1;
            facesRight = vx > 0;

            if (selectedIndex.value >= 0 && !look.value) {
                tileTransform = `rotate(${((vx / length) * 7).toFixed(1)}deg)`;
            }
        }

        return { option, isSelected, tileTransform, facesRight };
    });
});

function tileElements(): HTMLElement[] {
    return grid.value
        ? [...grid.value.querySelectorAll<HTMLElement>('[data-tile]')]
        : [];
}

/** Pulo em cascata pela distância até `from` (−9px, 55ms por unidade). */
function hopAll(from: number) {
    tileElements().forEach((tile, i) => {
        if (i === from) {
            return;
        }

        const distance =
            from < 0
                ? i
                : Math.hypot(
                      (i % COLUMNS) - (from % COLUMNS),
                      Math.floor(i / COLUMNS) - Math.floor(from / COLUMNS),
                  );

        animate(
            tile,
            [
                { translate: '0 0' },
                { translate: '0 -9px', offset: 0.4 },
                { translate: '0 0' },
            ],
            420,
            {
                delay: distance * 55,
                easing: 'ease-out',
                fill: 'none',
            },
        );
    });
}

function pick(index: number) {
    if (index === selectedIndex.value) {
        return;
    }

    selected.value = props.options[index].id;
    hopAll(index);
    const sprite = tileElements()[index]?.querySelector('img');

    if (sprite) {
        emit('picked', index, sprite);
    }
}

/** Tentou seguir sem escolher: todo mundo pula e a grade treme. */
function nudge() {
    hopAll(-1);
    shake(grid.value);
}

function onPointerMove(event: PointerEvent) {
    if (!grid.value) {
        return;
    }

    const rect = grid.value.getBoundingClientRect();
    const gridWidth = COLUMNS * TILE + (COLUMNS - 1) * GAP;
    look.value = {
        x: event.clientX - rect.left - (rect.width - gridWidth) / 2,
        y: event.clientY - rect.top - 6,
    };
}

onMounted(() => {
    idleTimer = window.setInterval(() => {
        const elements = tileElements();
        const index = Math.floor(Math.random() * elements.length);

        if (index === selectedIndex.value) {
            return;
        }

        animate(
            elements[index]?.querySelector('img'),
            [
                { translate: '0 0' },
                { translate: '0 -4px', offset: 0.45 },
                { translate: '0 0' },
            ],
            280,
            { easing: 'ease-out', fill: 'none' },
        );
    }, 900);
});

onBeforeUnmount(() => window.clearInterval(idleTimer));

defineExpose({ nudge });
</script>

<template>
    <div
        ref="grid"
        role="radiogroup"
        aria-label="Parceiro"
        class="grid grid-cols-[repeat(5,64px)] justify-center gap-[14px] py-1.5 max-[460px]:[zoom:0.74]"
        @pointermove="onPointerMove"
        @pointerleave="look = null"
    >
        <button
            v-for="(tile, index) in tiles"
            :key="tile.option.id"
            type="button"
            role="radio"
            data-tile
            :aria-checked="tile.isSelected"
            :aria-label="tile.option.name"
            class="relative flex h-16 w-16 cursor-pointer items-center justify-center rounded-full bg-white shadow-[0_0_0_2px_var(--color-line)] transition-transform duration-500 ease-[cubic-bezier(.34,1.56,.64,1)]"
            :style="{ transform: tile.tileTransform }"
            @click="pick(index)"
        >
            <span
                v-if="tile.isSelected"
                class="ring-mask pointer-events-none absolute -inset-1.5 overflow-hidden rounded-full p-[3.5px]"
                aria-hidden="true"
            >
                <span
                    class="animate-spin-ring absolute -inset-1/2 bg-[conic-gradient(#f24d4d,#ffffff,#f24d4d)]"
                />
            </span>
            <img
                :src="`/storage/${tile.option.sprite_path}`"
                alt=""
                width="96"
                height="96"
                class="pixelated pointer-events-none relative h-24 w-24 max-w-none transition-transform duration-[400ms] ease-[cubic-bezier(.34,1.56,.64,1)]"
                :style="{ transform: tile.facesRight ? 'scaleX(-1)' : 'none' }"
            />
        </button>
    </div>
</template>
