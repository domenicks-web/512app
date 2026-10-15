<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useMediaQuery } from '@/composables/useMediaQuery';
import PokeballMark from '@/components/entry/PokeballMark.vue';
import {
    holoBackground,
    holoPosition,
    IV_MAX,
    isGoldMint,
    natureColor,
    pad,
    panelBackground,
    sizeLabel,
    spriteOf,
    summarySubtitle,
    summaryTitle,
    TIER_META,
} from '@/lib/pack/meta';
import { vPixelFit } from '@/lib/pack/pixelFit';
import type { PackSpecimen } from '@/lib/pack/types';
import { formatCountdown } from '@/lib/format';
import { show as collectionShow } from '@/routes/collection';

/** Resumo do pack (tema claro): cartas com tilt e holo, contagem e comprovante. */
const props = defineProps<{
    specimens: PackSpecimen[];
    nextPackAt: string | null;
}>();
const emit = defineEmits<{ sheet: [index: number]; receipt: [] }>();

const isMobile = useMediaQuery('(max-width: 639.98px)');
const now = ref(Date.now());
let clock = 0;
onMounted(
    () => (clock = window.setInterval(() => (now.value = Date.now()), 1000)),
);
onBeforeUnmount(() => clearInterval(clock));

const countdown = computed(() =>
    props.nextPackAt
        ? formatCountdown(props.nextPackAt, now.value)
        : '00:00:00',
);

function tilt(event: MouseEvent) {
    const card = event.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const px = (event.clientX - rect.left) / rect.width;
    const py = (event.clientY - rect.top) / rect.height;
    card.style.transform = `perspective(800px) rotateX(${((0.5 - py) * 12).toFixed(2)}deg) rotateY(${((px - 0.5) * 14).toFixed(2)}deg) translateY(-6px)`;
    const holo = card.querySelector<HTMLElement>('[data-holo]');

    if (holo) {
        holo.style.backgroundPosition = `${(px * 100).toFixed(1)}% ${(py * 100).toFixed(1)}%`;
    }
}

function untilt(event: MouseEvent, specimen: PackSpecimen) {
    const card = event.currentTarget as HTMLElement;
    card.style.transform = '';
    const holo = card.querySelector<HTMLElement>('[data-holo]');

    if (holo) {
        holo.style.backgroundPosition = holoPosition(specimen);
    }
}
</script>

<template>
    <div
        class="mx-auto box-border flex max-w-[calc(880px+2*clamp(16px,5vw,56px))] flex-col gap-6 px-[clamp(16px,5vw,56px)] pt-[96px] pb-10 sm:gap-[30px] sm:pb-14"
    >
        <div data-k="sumHead" class="flex flex-col gap-2">
            <h1
                class="text-[clamp(30px,5vw,48px)] leading-[1.04] font-black tracking-[-0.035em] text-balance"
            >
                {{ summaryTitle(specimens) }}
            </h1>
            <div class="text-[15px] font-semibold text-[#6c737c]">
                {{ summarySubtitle(specimens) }}
            </div>
        </div>

        <div
            class="grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(150px,1fr))] sm:gap-4"
        >
            <button
                v-for="(specimen, index) in specimens"
                :key="specimen.id"
                type="button"
                data-card
                class="flex min-w-0 cursor-pointer flex-col gap-2.5 rounded-[22px] bg-white p-2 text-left opacity-0 shadow-[0_2px_3px_rgba(28,32,38,0.05),0_22px_48px_rgba(43,47,54,0.14)] transition-transform duration-[180ms] ease-out sm:gap-3 sm:rounded-[28px] sm:p-2.5"
                :aria-label="`Ver ficha de ${specimen.name}`"
                @mousemove="tilt"
                @mouseleave="untilt($event, specimen)"
                @click="emit('sheet', index)"
            >
                <div
                    class="relative flex h-[124px] items-center justify-center overflow-hidden rounded-[16px] sm:h-[178px] sm:rounded-[20px]"
                    :style="{
                        background: panelBackground(specimen),
                        boxShadow: specimen.isShiny
                            ? 'inset 0 0 0 2px #4dd9e8'
                            : 'none',
                    }"
                >
                    <img
                        v-pixel-fit="{
                            cap: isMobile ? 2 : 3,
                            maxH: isMobile ? 100 : 140,
                        }"
                        :src="spriteOf(specimen)"
                        :data-fallback="specimen.sprite"
                        :alt="specimen.name"
                        draggable="false"
                        class="relative block [image-rendering:pixelated]"
                    />
                    <div
                        data-holo
                        class="pointer-events-none absolute inset-0 bg-[length:240%_240%] mix-blend-screen transition-[background-position] duration-[180ms] ease-out"
                        :style="{
                            backgroundImage: holoBackground(specimen),
                            backgroundPosition: holoPosition(specimen),
                            opacity: specimen.isShiny ? 0.9 : 0.6,
                        }"
                    />
                    <div
                        v-if="specimen.isShiny"
                        class="absolute top-2.5 left-2.5 flex h-6 items-center gap-[5px] rounded-full bg-[#4dd9e8] px-[9px] text-[11px] font-extrabold text-[#1c1f26]"
                    >
                        <svg
                            width="10"
                            height="10"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 1l2.6 8.4L23 12l-8.4 2.6L12 23l-2.6-8.4L1 12l8.4-2.6z"
                            /></svg
                        >Shiny
                    </div>
                    <div
                        class="absolute top-[13px] right-3 font-mono text-[11px] font-extrabold"
                        :style="{
                            color: isGoldMint(specimen) ? '#FFC94D' : '#7d8794',
                        }"
                    >
                        Nº {{ pad(specimen.mint, 4) }}
                    </div>
                </div>
                <div
                    class="flex min-w-0 flex-col gap-2 px-1 pb-1 sm:gap-2.5 sm:px-1.5 sm:pb-1.5"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <div
                            class="truncate text-[15px] font-black tracking-[-0.02em] sm:text-base"
                        >
                            {{ specimen.name }}
                        </div>
                        <div
                            class="shrink-0 text-[11px] font-semibold text-[#8a9099]"
                        >
                            #{{ specimen.dex }}
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            class="flex h-[22px] items-center rounded-full px-2 text-[10px] font-extrabold sm:h-6 sm:px-[9px] sm:text-[11px]"
                            :style="{
                                background: TIER_META[specimen.tier].color,
                                color: TIER_META[specimen.tier].fg,
                            }"
                            >{{ TIER_META[specimen.tier].label }}</span
                        >
                        <span
                            class="flex h-[22px] items-center rounded-full bg-[#eff0f2] px-2 text-[10px] font-extrabold sm:h-6 sm:px-[9px] sm:text-[11px]"
                            >{{ sizeLabel(specimen) }}</span
                        >
                        <span
                            class="flex h-[22px] items-center gap-1.5 rounded-full bg-[#eff0f2] px-2 text-[10px] font-extrabold sm:h-6 sm:px-[9px] sm:text-[11px]"
                            ><span
                                class="size-[7px] rounded-full"
                                :style="{
                                    background: natureColor(
                                        specimen.natureIndex,
                                    ),
                                }"
                            />{{ specimen.nature }}</span
                        >
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div
                            class="text-xs font-extrabold whitespace-nowrap text-[#767c85]"
                        >
                            IV {{ specimen.ivTotal }}
                        </div>
                        <div
                            class="h-1.5 flex-1 overflow-hidden rounded-full bg-[#eef0f2]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.round((specimen.ivTotal / IV_MAX) * 100)}%`,
                                    background: natureColor(
                                        specimen.natureIndex,
                                    ),
                                }"
                            />
                        </div>
                    </div>
                </div>
            </button>
        </div>

        <div
            data-k="sumFoot"
            class="flex flex-wrap items-center justify-between gap-[18px] pt-1.5"
        >
            <div class="flex flex-col gap-1">
                <div class="text-xs font-extrabold text-[#767c85]">
                    Próximo pack em
                </div>
                <div
                    class="font-mono text-[26px] font-extrabold tracking-[-0.02em]"
                >
                    {{ countdown }}
                </div>
            </div>
            <div class="flex w-full flex-wrap gap-2.5 sm:w-auto">
                <button
                    type="button"
                    class="flex h-[52px] flex-1 cursor-pointer items-center justify-center gap-2 rounded-full bg-white px-5 text-[15px] font-extrabold shadow-[inset_0_0_0_2px_#e4e6ea] hover:shadow-[inset_0_0_0_2px_#c9ccd2] sm:flex-none"
                    @click="emit('receipt')"
                >
                    <svg
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="#3f8f6b"
                        stroke-width="2.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path
                            d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"
                        />
                        <path d="M8.5 12l2.5 2.5 4.5-5" />
                    </svg>
                    Comprovante
                </button>
                <Link
                    :href="collectionShow.url()"
                    class="flex h-[52px] flex-1 items-center justify-center gap-2.5 rounded-full bg-[#f24d4d] pr-[22px] pl-[18px] text-[15px] font-extrabold whitespace-nowrap text-white shadow-[0_10px_22px_rgba(242,77,77,0.42)] active:scale-[0.97] sm:flex-none"
                >
                    <PokeballMark />
                    Ver coleção
                </Link>
            </div>
        </div>
    </div>
</template>
