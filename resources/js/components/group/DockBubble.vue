<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import { animate, finished, springs } from '@/lib/motion';
import { rarityLook } from '@/lib/pack/rarity';
import type { Notice } from '@/lib/realtime/notice';

/**
 * Bolha de um aviso em tempo real, saindo do ícone do dock (`anchorX`,
 * em px dentro da linha do dock) com um rabinho apontando pra ele e presa
 * dentro da tela. Fica `DURATION` ms; segurar pausa, arrastar pra baixo
 * dispensa, tocar abre o destino. `more` é quantas ainda esperam na fila.
 * O destaque do feed usa a moldura da raridade.
 */
const props = defineProps<{
    notice: Notice;
    more: number;
    anchorX: number;
    rowWidth: number;
}>();
const emit = defineEmits<{ done: [] }>();

const DURATION = 4500;
const GUTTER = 12;

const root = ref<HTMLElement | null>(null);
const width = computed(() => Math.min(320, props.rowWidth - GUTTER * 2));
const left = computed(() =>
    Math.min(
        Math.max(props.anchorX - width.value / 2, GUTTER),
        props.rowWidth - width.value - GUTTER,
    ),
);
const tail = computed(() =>
    Math.min(Math.max(props.anchorX - left.value, 22), width.value - 22),
);
const look = computed(() =>
    props.notice.kind === 'feed_highlight' && props.notice.tier
        ? rarityLook(props.notice.tier, props.notice.isShiny)
        : null,
);

let remaining = DURATION;
let startedAt = 0;
let timer = 0;
let leaving = false;
let pressY: number | null = null;
let dragged = false;

function schedule() {
    startedAt = performance.now();
    timer = window.setTimeout(leave, remaining);
}

function pause() {
    window.clearTimeout(timer);
    remaining = Math.max(800, remaining - (performance.now() - startedAt));
}

async function leave() {
    if (leaving) {
        return;
    }

    leaving = true;
    window.clearTimeout(timer);
    await finished(
        animate(
            root.value,
            [
                { transform: 'none', opacity: 1 },
                { transform: 'translateY(18px) scale(.4)', opacity: 0 },
            ],
            260,
            { easing: 'cubic-bezier(.4,0,1,1)', fill: 'forwards' },
        ),
    );
    emit('done');
}

function onDown(event: PointerEvent) {
    pressY = event.clientY;
    dragged = false;
    pause();
}

function onMove(event: PointerEvent) {
    if (pressY === null || !root.value) {
        return;
    }

    const dy = Math.max(0, event.clientY - pressY);
    dragged = dragged || dy > 6;
    root.value.style.transform = `translateY(${dy}px)`;
    root.value.style.opacity = String(Math.max(0.2, 1 - dy / 90));
}

function onUp(event: PointerEvent) {
    if (pressY === null) {
        return;
    }

    const dy = event.clientY - pressY;
    pressY = null;

    if (dy > 40) {
        void leave();

        return;
    }

    if (root.value) {
        root.value.style.transform = '';
        root.value.style.opacity = '';
    }

    if (!dragged) {
        router.visit(props.notice.href);
        void leave();

        return;
    }

    schedule();
}

onMounted(() => {
    animate(
        root.value,
        [
            { transform: 'translateY(26px) scale(.35)', opacity: 0 },
            { transform: 'none', opacity: 1 },
        ],
        560,
        { easing: springs.pop() },
    );
    schedule();
});

onBeforeUnmount(() => window.clearTimeout(timer));
</script>

<template>
    <div
        ref="root"
        role="status"
        aria-live="polite"
        class="absolute bottom-full z-20 mb-1 cursor-pointer touch-none select-none"
        :style="{
            left: `${left}px`,
            width: `${width}px`,
            transformOrigin: `${tail}px 100%`,
        }"
        @pointerdown="onDown"
        @pointermove="onMove"
        @pointerup="onUp"
        @pointercancel="onUp"
    >
        <div
            class="relative rounded-[22px] p-[2px] shadow-[0_14px_34px_rgba(28,32,38,0.22),0_2px_6px_rgba(28,32,38,0.08)]"
            :style="{ background: look ? look.frame : '#ffffff' }"
        >
            <div
                class="flex items-center gap-3 rounded-[20px] bg-white px-3 py-2.5"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full"
                    :style="{ background: look ? look.panel : '#f4f5f6' }"
                >
                    <FittedSprite
                        v-if="notice.spritePath"
                        :src="`/storage/${notice.spritePath}`"
                        alt=""
                        :size="44"
                        :padding="4"
                    />
                </span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span
                        class="truncate text-[14px] leading-tight font-black text-[#2b2f36]"
                        >{{ notice.title }}</span
                    >
                    <span
                        v-if="notice.body"
                        class="truncate text-[12.5px] font-bold text-[#5a6069]"
                        >{{ notice.body }}</span
                    >
                </span>
                <span
                    v-if="more > 0"
                    class="bg-brand shrink-0 rounded-full px-2 py-0.5 text-[11px] font-black text-white"
                    >+{{ more }}</span
                >
            </div>
            <span
                aria-hidden="true"
                class="absolute -bottom-[5px] size-3 -translate-x-1/2 rotate-45 rounded-[2px]"
                :style="{
                    left: `${tail}px`,
                    background: look ? look.frame : '#ffffff',
                }"
            />
        </div>
    </div>
</template>
