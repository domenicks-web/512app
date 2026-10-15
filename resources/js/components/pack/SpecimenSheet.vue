<script setup lang="ts">
import { computed } from 'vue';
import { useMediaQuery } from '@/composables/useMediaQuery';
import { formatDayMonth } from '@/lib/format';
import {
    holoBackground,
    holoPosition,
    IV_MAX,
    isGoldMint,
    natureColor,
    pad,
    panelBackground,
    shortHash,
    sizeLabel,
    sizeText,
    spriteOf,
    STATS,
    TIER_META,
} from '@/lib/pack/meta';
import { vPixelFit } from '@/lib/pack/pixelFit';
import { rarityLook } from '@/lib/pack/rarity';
import type { PackSpecimen } from '@/lib/pack/types';
import PackModal from './PackModal.vue';

/**
 * Ficha do espécime: sprite até 4×, régua de tamanho, IVs, natureza,
 * captura e hash. Escura na abertura do pack; clara (`light`) na coleção,
 * com o painel no estilo da raridade (o mesmo da carta).
 */
const props = defineProps<{
    specimen: PackSpecimen | null;
    /** Mais de um espécime da espécie (coleção): "2 de 3" com setas. */
    position?: { index: number; count: number } | null;
    light?: boolean;
}>();
const isMobile = useMediaQuery('(max-width: 639.98px)');
const emit = defineEmits<{ close: []; prev: []; next: [] }>();

const caught = computed(() =>
    props.specimen
        ? new Date(props.specimen.caughtAt)
              .toLocaleDateString('pt-BR', {
                  day: '2-digit',
                  month: 'short',
                  year: 'numeric',
              })
              .replace('.', '')
        : '',
);

const look = computed(() =>
    props.specimen
        ? rarityLook(props.specimen.tier, props.specimen.isShiny)
        : null,
);

/** Cores que mudam entre a ficha escura e a clara. */
const tone = computed(() =>
    props.light
        ? {
              muted: 'text-[#767c85]',
              soft: 'text-[#5a6069]',
              track: 'bg-[#eef0f2]',
              band: 'bg-[#e4e7eb]',
              knob: 'bg-[#2b2f36] shadow-[0_0_0_4px_rgba(43,47,54,0.12)]',
              chip: 'bg-[#f4f5f6]',
              box: 'bg-[#f4f5f6]',
              gold: '#a87700',
              value: '#2b2f36',
              verified: 'bg-[#e3f1ea] text-[#2f7556]',
          }
        : {
              muted: 'text-[#7d8794]',
              soft: 'text-[#c3cad4]',
              track: 'bg-white/8',
              band: 'bg-white/6',
              knob: 'bg-white shadow-[0_0_0_4px_rgba(255,255,255,0.15)]',
              chip: 'bg-white/8',
              box: 'bg-white/5',
              gold: '#FFC94D',
              value: '#ffffff',
              verified: 'bg-[rgba(63,143,107,0.2)] text-[#6fd0a2]',
          },
);
</script>

<template>
    <PackModal
        :open="specimen !== null"
        :label="specimen ? `Ficha de ${specimen.name}` : 'Ficha'"
        max-width="780px"
        :light="light"
        @close="emit('close')"
    >
        <div v-if="specimen && look" class="flex flex-wrap">
            <div
                class="relative flex min-h-[190px] flex-[1_1_300px] items-center justify-center overflow-hidden sm:min-h-[340px]"
                :style="{
                    background: light
                        ? (look.card.panel ?? look.panel)
                        : panelBackground(specimen),
                }"
            >
                <div
                    v-if="light"
                    class="absolute inset-x-0 top-0 h-1"
                    :class="
                        specimen.isShiny ? 'motion-safe:animate-foil-drift' : ''
                    "
                    :style="{
                        background: look.frame,
                        backgroundSize: specimen.isShiny
                            ? '300% 100%'
                            : undefined,
                    }"
                />
                <div
                    v-if="light && look.card.foil === 'holo'"
                    class="card-foil card-foil-holo"
                />
                <div
                    v-if="
                        light &&
                        (look.card.foil === 'holo' || look.card.foil === 'gold')
                    "
                    class="card-foil card-foil-stars"
                />
                <div
                    v-if="light && look.card.foil === 'rainbow'"
                    class="card-foil card-foil-rainbow opacity-60"
                />
                <div
                    v-if="light"
                    class="absolute bottom-[18%] left-1/2 h-4 w-40 -translate-x-1/2 rounded-[50%] blur-[5px]"
                    :class="specimen.isShiny ? 'bg-black/40' : 'bg-black/12'"
                />
                <img
                    v-pixel-fit="{
                        cap: isMobile ? 3 : 4,
                        maxH: isMobile ? 160 : 260,
                        maxW: 300,
                    }"
                    :src="spriteOf(specimen)"
                    :data-fallback="specimen.sprite"
                    :alt="specimen.name"
                    draggable="false"
                    class="relative block [image-rendering:pixelated]"
                />
                <template v-if="light && look.card.foil === 'rainbow'">
                    <span
                        v-for="spark in 3"
                        :key="spark"
                        class="card-sparkle [--spark:20px]"
                        :class="`card-sparkle-${spark}`"
                        aria-hidden="true"
                        >✦</span
                    >
                </template>
                <span
                    v-if="light && specimen.mint === 1"
                    class="absolute top-4 left-4 flex size-[46px] -rotate-12 flex-col items-center justify-center rounded-full border-2 leading-none font-black shadow-[0_2px_5px_rgba(0,0,0,0.25)]"
                    :class="
                        specimen.isShiny
                            ? 'border-white bg-black/45 text-white'
                            : 'border-[#2b2f36] bg-white/90 text-[#2b2f36]'
                    "
                    title="Primeiro do servidor"
                    aria-label="Primeiro do servidor"
                >
                    <span class="text-[9px] tracking-[0.06em]">Nº</span>
                    <span class="-mt-px text-[20px]">1</span>
                </span>
                <div
                    v-if="!light"
                    class="pointer-events-none absolute inset-0 bg-[length:240%_240%] mix-blend-screen"
                    :style="{
                        backgroundImage: holoBackground(specimen),
                        backgroundPosition: holoPosition(specimen),
                        opacity: specimen.isShiny ? 0.45 : 0.4,
                    }"
                />
                <div
                    v-else-if="look.sheen"
                    class="motion-safe:animate-foil-sweep pointer-events-none absolute inset-0 bg-[linear-gradient(115deg,rgba(255,255,255,0)_38%,rgba(255,255,255,0.7)_50%,rgba(255,255,255,0)_62%)] bg-[length:250%_100%] bg-[position:160%_0] mix-blend-overlay"
                />
                <div
                    v-if="position && position.count > 1"
                    class="absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full bg-black/40 p-1 text-xs font-extrabold text-white backdrop-blur-sm"
                >
                    <button
                        type="button"
                        class="flex size-8 cursor-pointer items-center justify-center rounded-full hover:bg-white/10"
                        aria-label="Espécime anterior"
                        @click="emit('prev')"
                    >
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M15 6l-6 6 6 6" />
                        </svg>
                    </button>
                    <span class="min-w-[44px] text-center tabular-nums"
                        >{{ position.index + 1 }} de {{ position.count }}</span
                    >
                    <button
                        type="button"
                        class="flex size-8 cursor-pointer items-center justify-center rounded-full hover:bg-white/10"
                        aria-label="Próximo espécime"
                        @click="emit('next')"
                    >
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M9 6l6 6-6 6" />
                        </svg>
                    </button>
                </div>
            </div>

            <div
                class="flex min-w-0 flex-[1_1_360px] flex-col gap-4 px-5 pt-5 pb-5 sm:gap-[22px] sm:px-7 sm:pt-[30px] sm:pb-7"
            >
                <div class="flex flex-col gap-2.5">
                    <div class="flex items-baseline gap-2.5">
                        <div
                            class="text-[28px] leading-none font-black tracking-[-0.03em] sm:text-[34px]"
                        >
                            {{ specimen.name }}
                        </div>
                        <div class="text-sm font-semibold" :class="tone.muted">
                            #{{ specimen.dex }}
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            class="flex h-[26px] items-center rounded-full px-2.5 text-xs font-extrabold"
                            :style="
                                light
                                    ? {
                                          background: look.badge,
                                          color: look.badgeInk,
                                      }
                                    : {
                                          background:
                                              TIER_META[specimen.tier].color,
                                          color: TIER_META[specimen.tier].fg,
                                      }
                            "
                            >{{ TIER_META[specimen.tier].label }}</span
                        >
                        <span
                            v-if="specimen.isShiny"
                            class="flex h-[26px] items-center rounded-full bg-[#4dd9e8] px-2.5 text-xs font-extrabold text-[#1c1f26]"
                            >Shiny</span
                        >
                        <span
                            class="flex h-[26px] items-center rounded-full px-2.5 font-mono text-xs font-extrabold"
                            :class="tone.chip"
                            :style="{
                                color: isGoldMint(specimen)
                                    ? tone.gold
                                    : light
                                      ? '#5a6069'
                                      : '#c3cad4',
                            }"
                            >Nº {{ pad(specimen.mint, 4) }}</span
                        >
                    </div>
                </div>

                <div class="flex flex-col gap-2.5">
                    <div
                        class="flex justify-between gap-2.5 text-xs font-extrabold"
                    >
                        <span :class="tone.soft"
                            >Tamanho {{ sizeLabel(specimen) }}</span
                        >
                        <span class="font-semibold" :class="tone.muted">{{
                            sizeText(specimen)
                        }}</span>
                    </div>
                    <div class="relative h-2 rounded-full" :class="tone.track">
                        <div
                            class="absolute top-0 right-[20%] bottom-0 left-[20%]"
                            :class="tone.band"
                        />
                        <div
                            class="absolute top-1/2 -mt-2 -ml-2 size-4 rounded-full"
                            :class="tone.knob"
                            :style="{
                                left: `${(specimen.sizeRoll * 100).toFixed(1)}%`,
                            }"
                        />
                    </div>
                    <div
                        class="relative h-3.5 text-[10px] font-extrabold"
                        :class="tone.muted"
                    >
                        <span class="absolute left-0">XXS</span
                        ><span class="absolute left-[11%] -translate-x-1/2"
                            >XS</span
                        ><span class="absolute left-1/2 -translate-x-1/2"
                            >M</span
                        ><span class="absolute left-[89%] -translate-x-1/2"
                            >XL</span
                        ><span class="absolute right-0">XXL</span>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5 sm:gap-[9px]">
                    <div class="flex justify-between text-xs font-extrabold">
                        <span :class="tone.soft">IVs</span
                        ><span>{{ specimen.ivTotal }} / {{ IV_MAX }}</span>
                    </div>
                    <div
                        v-for="(value, k) in specimen.ivs"
                        :key="k"
                        class="grid grid-cols-[34px_minmax(0,1fr)_22px] items-center gap-2.5"
                    >
                        <div
                            class="text-[11px] font-extrabold"
                            :class="tone.muted"
                        >
                            {{ STATS[k] }}
                        </div>
                        <div
                            class="h-1.5 overflow-hidden rounded-full"
                            :class="tone.track"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.round((value / 31) * 100)}%`,
                                    background: natureColor(
                                        specimen.natureIndex,
                                    ),
                                }"
                            />
                        </div>
                        <div
                            class="text-right text-xs font-extrabold"
                            :style="{
                                color: value === 31 ? tone.gold : tone.value,
                            }"
                        >
                            {{ value }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3.5">
                    <div class="flex flex-col gap-[5px]">
                        <div
                            class="text-[11px] font-extrabold"
                            :class="tone.muted"
                        >
                            Natureza
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm font-extrabold"
                        >
                            <span
                                class="size-[9px] rounded-full"
                                :style="{
                                    background: natureColor(
                                        specimen.natureIndex,
                                    ),
                                }"
                            />{{ specimen.nature }}
                        </div>
                        <div class="text-[11px] font-bold" :class="tone.muted">
                            {{ specimen.natureEffect }}
                        </div>
                    </div>
                    <div class="flex flex-col gap-[5px]">
                        <div
                            class="text-[11px] font-extrabold"
                            :class="tone.muted"
                        >
                            Capturado
                        </div>
                        <div class="text-sm font-extrabold">{{ caught }}</div>
                    </div>
                </div>

                <div
                    v-if="light && specimen.history?.length"
                    class="flex flex-col gap-2"
                >
                    <div class="text-[11px] font-extrabold" :class="tone.muted">
                        Histórico
                    </div>
                    <ol
                        class="flex flex-col gap-1.5 border-l-2 border-[#e4e7eb] pl-3"
                    >
                        <li
                            v-for="(step, k) in specimen.history"
                            :key="k"
                            class="text-[13px] font-bold"
                        >
                            <span :style="{ color: tone.value }">{{
                                step.kind === 'caught'
                                    ? `Capturado por ${step.nickname}`
                                    : step.kind === 'released'
                                      ? `Libertado: era de ${step.fromNickname}`
                                      : `Trocado: ${step.fromNickname} → ${step.nickname}`
                            }}</span>
                            <span :class="tone.muted">
                                · {{ formatDayMonth(step.at) }}</span
                            >
                        </li>
                    </ol>
                </div>

                <div
                    class="flex items-center justify-between gap-2.5 rounded-2xl px-3.5 py-3"
                    :class="tone.box"
                >
                    <div class="flex min-w-0 flex-col gap-[3px]">
                        <div
                            class="text-[11px] font-extrabold"
                            :class="tone.muted"
                        >
                            Hash do espécime
                        </div>
                        <div
                            class="font-mono text-sm font-extrabold"
                            :class="tone.soft"
                        >
                            {{ shortHash(specimen.hash) }}
                        </div>
                    </div>
                    <span
                        class="flex h-[26px] items-center gap-1.5 rounded-full px-2.5 text-xs font-extrabold whitespace-nowrap"
                        :class="tone.verified"
                    >
                        <svg
                            width="11"
                            height="11"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3.4"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M5 12.5l4.5 4.5L19 7.5" /></svg
                        >Verificado
                    </span>
                </div>
                <slot name="actions" :specimen="specimen" />
            </div>
        </div>
    </PackModal>
</template>
