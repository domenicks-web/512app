<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useDotField } from '@/composables/useDotField';
import { animate, spring } from '@/lib/motion';

const model = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        label: string;
        name: string;
        autocomplete?: string;
        error?: string | null;
    }>(),
    { autocomplete: 'current-password', error: null },
);

/** Só os primeiros caracteres cabem no campo; o resto continua no input. */
const VISIBLE_SLOTS = 24;

const input = ref<HTMLInputElement | null>(null);
const row = ref<HTMLElement | null>(null);
const eye = ref<SVGElement | null>(null);
const errorPill = ref<HTMLElement | null>(null);
const isVisible = ref(false);
const isFocused = ref(false);
const selection = ref<[number, number]>([0, 0]);
const field = useDotField();

const slots = computed(() =>
    Array.from(model.value.slice(0, VISIBLE_SLOTS), (char, index) => ({
        char,
        isSelected:
            isFocused.value &&
            selection.value[1] > selection.value[0] &&
            index >= selection.value[0] &&
            index < selection.value[1],
    })),
);

function onInput(event: Event) {
    const target = event.target as HTMLInputElement;
    model.value = target.value;
    syncSelection();
    field.rippleFromInput(
        target,
        Math.min(VISIBLE_SLOTS, target.value.length) * 19,
    );
}

function syncSelection() {
    const element = input.value;

    if (element) {
        selection.value = [
            element.selectionStart ?? 0,
            element.selectionEnd ?? 0,
        ];
    }
}

// Cada caractere novo cai como uma mini-pokébola (mola 320,13).
watch(
    () => model.value.length,
    async (length, previous) => {
        if (length <= previous || length > VISIBLE_SLOTS) {
            return;
        }

        await nextTick();
        animate(
            row.value?.lastElementChild,
            [
                {
                    transform: 'translateY(-16px) scale(.3) rotate(-50deg)',
                    opacity: 0,
                },
                { transform: 'none', opacity: 1 },
            ],
            520,
            { easing: spring(320, 13) },
        );
    },
);

watch(
    () => props.error,
    async (error, previous) => {
        if (!error || error === previous) {
            return;
        }

        await nextTick();
        animate(
            errorPill.value,
            [
                { transform: 'translateY(-8px) scale(.6)', opacity: 0 },
                { transform: 'none', opacity: 1 },
            ],
            520,
            { easing: spring(300, 14) },
        );
    },
);

/**
 * Mostrar: o olho pisca e cada pokébola, em cascata de 38ms, gira 200° e
 * some; o caractere real sai dela com um pulinho. Esconder é o inverso.
 */
function toggleVisibility() {
    isVisible.value = !isVisible.value;
    const show = isVisible.value;

    animate(
        eye.value,
        [
            { transform: 'none' },
            { transform: 'scaleY(.1)', offset: 0.4 },
            { transform: 'scale(1.15)', offset: 0.7 },
            { transform: 'none' },
        ],
        380,
        { easing: 'ease-out', fill: 'none' },
    );

    [...(row.value?.children ?? [])].forEach((slot, index) => {
        const ball = slot.querySelector('[data-p="ball"]');
        const char = slot.querySelector('[data-p="ch"]');
        const delay = index * 38;

        if (show) {
            animate(
                ball,
                [
                    { transform: 'none', opacity: 1 },
                    {
                        transform: 'translateY(-9px) rotate(200deg) scale(.2)',
                        opacity: 0,
                    },
                ],
                260,
                { delay, easing: 'cubic-bezier(.5,0,.8,.4)', fill: 'both' },
            );
            animate(
                char,
                [
                    { transform: 'translateY(8px) scale(.2)', opacity: 0 },
                    {
                        transform: 'translateY(-3px) scale(1.25)',
                        opacity: 1,
                        offset: 0.55,
                    },
                    { transform: 'none', opacity: 1 },
                ],
                460,
                { delay: delay + 120, easing: 'ease-out', fill: 'both' },
            );
        } else {
            animate(
                char,
                [
                    { transform: 'none', opacity: 1 },
                    { transform: 'translateY(-8px) scale(.3)', opacity: 0 },
                ],
                200,
                { delay, easing: 'ease-in', fill: 'both' },
            );
            animate(
                ball,
                [
                    {
                        transform: 'translateY(-16px) scale(.3) rotate(-50deg)',
                        opacity: 0,
                    },
                    { transform: 'none', opacity: 1 },
                ],
                520,
                { delay: delay + 90, easing: spring(320, 13), fill: 'both' },
            );
        }
    });
}

defineExpose({ input });
</script>

<template>
    <div class="flex flex-col gap-2">
        <label
            :for="`field-${name}`"
            class="text-muted text-[13.5px] font-extrabold"
        >
            {{ label }}
        </label>
        <div class="relative">
            <input
                :id="`field-${name}`"
                ref="input"
                :value="model"
                type="password"
                :name="name"
                :autocomplete="autocomplete"
                class="entry-input entry-input--masked pr-14"
                :class="{ 'entry-input--error': error }"
                @input="onInput"
                @select="syncSelection"
                @keyup="syncSelection"
                @click="syncSelection"
                @focus="isFocused = true"
                @blur="isFocused = false"
            />
            <div
                class="pointer-events-none absolute inset-y-0 right-[58px] left-[22px] flex items-center gap-[5px] overflow-hidden"
                aria-hidden="true"
            >
                <div ref="row" class="flex items-center gap-[5px]">
                    <div
                        v-for="(slot, index) in slots"
                        :key="index"
                        class="relative h-6 w-3.5 shrink-0 rounded-md transition-[background-color,box-shadow] duration-150"
                        :style="{
                            background: slot.isSelected
                                ? 'rgba(242,77,77,0.22)'
                                : 'transparent',
                            boxShadow: `0 0 0 2.5px ${slot.isSelected ? 'rgba(242,77,77,0.22)' : 'transparent'}`,
                        }"
                    >
                        <svg
                            data-p="ball"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            class="absolute top-[5px] left-0 block"
                            :style="{ opacity: isVisible ? 0 : 1 }"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="10.8"
                                fill="#ffffff"
                                stroke="#434a53"
                                stroke-width="1.6"
                            />
                            <path
                                d="M1.2 12a10.8 10.8 0 0 1 21.6 0z"
                                fill="#f24d4d"
                            />
                            <rect
                                x="1.2"
                                y="10.3"
                                width="21.6"
                                height="3.4"
                                fill="#434a53"
                            />
                            <circle cx="12" cy="12" r="4.6" fill="#434a53" />
                            <circle cx="12" cy="12" r="2.3" fill="#e4e7eb" />
                        </svg>
                        <div
                            data-p="ch"
                            class="text-ink absolute inset-0 flex items-center justify-center text-[17px] font-extrabold"
                            :style="{ opacity: isVisible ? 1 : 0 }"
                        >
                            {{ slot.char }}
                        </div>
                    </div>
                </div>
                <div
                    v-if="isFocused"
                    class="bg-brand animate-caret h-5 w-0.5 rounded-sm"
                />
            </div>
            <button
                type="button"
                class="absolute top-1.5 right-1.5 flex h-10 w-10 cursor-pointer items-center justify-center rounded-full p-0 transition-[background-color,color,transform] duration-300 hover:bg-[rgba(43,47,54,0.06)] active:scale-90"
                :class="isVisible ? 'text-brand' : 'text-[#8a9099]'"
                :aria-label="isVisible ? 'Esconder senha' : 'Mostrar senha'"
                :aria-pressed="isVisible"
                @mousedown.prevent
                @click="toggleVisibility"
            >
                <svg
                    ref="eye"
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="block overflow-visible"
                >
                    <path
                        d="M2.5 12c2.3-4.2 5.5-6.5 9.5-6.5s7.2 2.3 9.5 6.5c-2.3 4.2-5.5 6.5-9.5 6.5S4.8 16.2 2.5 12z"
                    />
                    <circle
                        cx="12"
                        cy="12"
                        r="3.6"
                        fill="currentColor"
                        stroke="none"
                    />
                    <path
                        d="M8.4 12a3.6 3.6 0 0 1 7.2 0z"
                        :fill="isVisible ? '#f24d4d' : 'currentColor'"
                        stroke="none"
                    />
                    <circle
                        cx="12"
                        cy="12"
                        r="1.3"
                        fill="#f4f5f6"
                        stroke="none"
                    />
                    <path
                        d="M4 4l16 16"
                        class="transition-[stroke-dashoffset] duration-300"
                        :style="{
                            strokeDasharray: 23,
                            strokeDashoffset: isVisible ? 23 : 0,
                        }"
                    />
                </svg>
            </button>
        </div>
        <span
            v-if="error"
            ref="errorPill"
            role="alert"
            class="text-brand-ink self-start rounded-full bg-[rgba(242,77,77,0.12)] px-3 py-1.5 text-[12.5px] font-extrabold"
        >
            {{ error }}
        </span>
    </div>
</template>
