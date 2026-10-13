<script setup lang="ts">
import { computed } from 'vue';

/**
 * Três rodas (dia, mês, ano) que funcionam com scroll (throttle de 70ms) ou
 * toque. O valor sai como `YYYY-MM-DD`; o dia é limitado ao tamanho do mês.
 */
const props = defineProps<{
    yearRange: [number, number];
}>();

const day = defineModel<number>('day', { required: true });
const month = defineModel<number>('month', { required: true });
const year = defineModel<number>('year', { required: true });

const MONTHS = [
    'Jan',
    'Fev',
    'Mar',
    'Abr',
    'Mai',
    'Jun',
    'Jul',
    'Ago',
    'Set',
    'Out',
    'Nov',
    'Dez',
];
const ROW_HEIGHT = 46;

const years = computed(() =>
    Array.from(
        { length: props.yearRange[1] - props.yearRange[0] + 1 },
        (_, i) => String(props.yearRange[0] + i),
    ),
);
const daysInMonth = computed(() =>
    new Date(year.value, month.value + 1, 0).getDate(),
);
const days = computed(() =>
    Array.from({ length: daysInMonth.value }, (_, i) =>
        String(i + 1).padStart(2, '0'),
    ),
);

let lastWheelAt = 0;

type Wheel = {
    key: string;
    items: string[];
    selected: number;
    set: (index: number) => void;
};

const wheels = computed<Wheel[]>(() => [
    {
        key: 'day',
        items: days.value,
        selected: day.value - 1,
        set: (i) => (day.value = i + 1),
    },
    {
        key: 'month',
        items: MONTHS,
        selected: month.value,
        set: (i) => {
            month.value = i;
            clampDay();
        },
    },
    {
        key: 'year',
        items: years.value,
        selected: year.value - props.yearRange[0],
        set: (i) => {
            year.value = props.yearRange[0] + i;
            clampDay();
        },
    },
]);

function clampDay() {
    const max = new Date(year.value, month.value + 1, 0).getDate();

    if (day.value > max) {
        day.value = max;
    }
}

function onWheel(wheel: Wheel, event: WheelEvent) {
    const now = performance.now();

    if (now - lastWheelAt < 70) {
        return;
    }

    lastWheelAt = now;
    const next = wheel.selected + (event.deltaY > 0 ? 1 : -1);
    wheel.set(Math.max(0, Math.min(wheel.items.length - 1, next)));
}

// Arrastar no toque: cada 30px de deslocamento anda uma linha.
let touchStartY: number | null = null;

function onTouchStart(event: TouchEvent) {
    touchStartY = event.touches[0]?.clientY ?? null;
}

function onTouchMove(wheel: Wheel, event: TouchEvent) {
    const y = event.touches[0]?.clientY;

    if (touchStartY === null || y === undefined) {
        return;
    }

    const steps = Math.trunc((touchStartY - y) / 30);

    if (steps !== 0) {
        touchStartY = y;
        wheel.set(
            Math.max(
                0,
                Math.min(wheel.items.length - 1, wheel.selected + steps),
            ),
        );
    }
}

function onKey(wheel: Wheel, event: KeyboardEvent) {
    const delta =
        event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : 0;

    if (delta === 0) {
        return;
    }

    event.preventDefault();
    wheel.set(
        Math.max(0, Math.min(wheel.items.length - 1, wheel.selected + delta)),
    );
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-muted text-[13.5px] font-extrabold"
            >Data de nascimento</span
        >
        <div
            class="bg-field relative grid h-[138px] grid-cols-[1fr_1.3fr_1.5fr] gap-2 overflow-hidden rounded-[22px] px-2"
        >
            <div
                class="absolute top-[46px] right-2 left-2 h-[46px] rounded-[14px] bg-white shadow-[0_1px_2px_rgba(28,32,38,0.06),0_4px_12px_rgba(28,32,38,0.06)]"
            />
            <div
                v-for="wheel in wheels"
                :key="wheel.key"
                role="spinbutton"
                tabindex="0"
                :aria-label="
                    wheel.key === 'day'
                        ? 'Dia'
                        : wheel.key === 'month'
                          ? 'Mês'
                          : 'Ano'
                "
                :aria-valuetext="wheel.items[wheel.selected]"
                class="relative cursor-ns-resize overflow-hidden rounded-[14px] outline-none focus-visible:ring-2 focus-visible:ring-[rgba(242,77,77,0.35)]"
                @wheel.prevent="onWheel(wheel, $event)"
                @touchstart.passive="onTouchStart"
                @touchmove.prevent="onTouchMove(wheel, $event)"
                @keydown="onKey(wheel, $event)"
            >
                <div
                    class="transition-transform duration-500 ease-[cubic-bezier(.34,1.56,.64,1)]"
                    :style="{
                        transform: `translateY(${(1 - wheel.selected) * ROW_HEIGHT}px)`,
                    }"
                >
                    <div
                        v-for="(label, index) in wheel.items"
                        :key="label"
                        class="flex h-[46px] cursor-pointer items-center justify-center text-[21px] font-black transition-[transform,color] duration-300 ease-[cubic-bezier(.34,1.56,.64,1)]"
                        :class="
                            index === wheel.selected
                                ? 'text-ink scale-100'
                                : 'scale-[.78] text-[#b9bdc4]'
                        "
                        @click="wheel.set(index)"
                    >
                        {{ label }}
                    </div>
                </div>
            </div>
        </div>
        <span class="text-faint text-center text-xs font-semibold"
            >Role ou toque nos números</span
        >
    </div>
</template>
