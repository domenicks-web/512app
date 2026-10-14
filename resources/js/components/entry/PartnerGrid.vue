<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { animate, shake } from '@/lib/motion';

/**
 * Grade 5×3 de parceiros com os sprites reais. O escolhido cresce com o
 * anel girando e os outros pulam em cascata; um sprite aleatório dá um
 * micro-pulo a cada ~900ms. Os sprites não seguem o cursor (tirado a
 * pedido do usuário).
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

const grid = ref<HTMLElement | null>(null);
let idleTimer = 0;

const selectedIndex = computed(() =>
    props.options.findIndex((option) => option.id === selected.value),
);

const tiles = computed(() =>
    props.options.map((option, i) => {
        const isSelected = i === selectedIndex.value;

        return {
            option,
            isSelected,
            tileTransform: isSelected ? 'scale(1.12)' : 'none',
        };
    }),
);

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
                class="pixelated pointer-events-none relative h-24 w-24 max-w-none"
            />
        </button>
    </div>
</template>
