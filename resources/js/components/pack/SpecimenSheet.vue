<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import FirstMintSeal from '@/components/FirstMintSeal.vue';
import { useMediaQuery } from '@/composables/useMediaQuery';
import {
    holoBackground,
    holoPosition,
    IV_MAX,
    isGoldMint,
    natureColor,
    pad,
    panelBackground,
    sizeLabel,
    sizeText,
    spriteOf,
    STATS,
    TIER_META,
} from '@/lib/pack/meta';
import { vCardTilt } from '@/lib/cardTilt';
import { vPixelFit } from '@/lib/pack/pixelFit';
import { rarityLook } from '@/lib/pack/rarity';
import type { PackSpecimen } from '@/lib/pack/types';
import NicknameForm from './NicknameForm.vue';
import PackModal from './PackModal.vue';
import SpecimenDetails from './SpecimenDetails.vue';
import { show as verifyShow } from '@/routes/verify';

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

/** Apelidos dados agora, por espécime: a ficha é JSON e não recarrega. */
const namedNow = ref<Record<number, string>>({});

/** No celular, histórico e hash viram botões que abrem por cima. */
const compactDetails = computed(() => isMobile.value);
const detail = ref<'history' | 'hash' | null>(null);
watch(
    () => props.specimen?.id,
    () => (detail.value = null),
);

function nicknameOf(specimen: PackSpecimen): string | null {
    return namedNow.value[specimen.id] ?? specimen.nickname ?? null;
}

/** No último dono (o atual), vale o apelido dado agora. */
function historyNickname(specimen: PackSpecimen, k: number): string | null {
    const isCurrent = k === (specimen.history?.length ?? 0) - 1;

    return (
        (isCurrent ? namedNow.value[specimen.id] : null) ??
        specimen.history?.[k]?.specimenNickname ??
        null
    );
}

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
        :gyro="light && isMobile"
        @close="emit('close')"
    >
        <div v-if="specimen && look" class="flex flex-wrap">
            <div
                v-card-tilt.gyro="!!light"
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
                    class="card-foil card-foil-holo card-foil-gyro"
                />
                <div
                    v-if="
                        light &&
                        (look.card.foil === 'holo' || look.card.foil === 'gold')
                    "
                    class="card-foil card-foil-stars card-foil-gyro"
                />
                <div
                    v-if="light && look.card.foil === 'rainbow'"
                    class="card-foil card-foil-rainbow card-foil-gyro opacity-60"
                />
                <div
                    v-if="light"
                    class="card-glare"
                    :class="
                        specimen.isShiny
                            ? 'card-glare-rainbow'
                            : specimen.tier === 'lendario'
                              ? 'card-glare-gold'
                              : ''
                    "
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
                <FirstMintSeal
                    v-if="light && specimen.mint === 1"
                    size="lg"
                    class="absolute top-4 left-4"
                />
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
                    <div
                        v-if="nicknameOf(specimen)"
                        class="flex flex-col gap-1"
                    >
                        <div
                            class="text-[28px] leading-none font-black tracking-[-0.03em] break-words sm:text-[34px]"
                        >
                            {{ nicknameOf(specimen) }}
                        </div>
                        <div class="text-sm font-bold" :class="tone.muted">
                            {{ specimen.name }} · #{{ specimen.dex }}
                        </div>
                    </div>
                    <div v-else class="flex items-baseline gap-2.5">
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
                    <NicknameForm
                        v-if="
                            light && specimen.canName && !namedNow[specimen.id]
                        "
                        :key="specimen.id"
                        :specimen-id="specimen.id"
                        :species-name="specimen.name"
                        :max-length="specimen.nicknameMaxLength ?? 16"
                        @named="(name) => (namedNow[specimen!.id] = name)"
                    />
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

                <template v-if="compactDetails">
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-if="light && specimen.history?.length"
                            type="button"
                            class="flex h-11 cursor-pointer items-center justify-center gap-1.5 rounded-full text-[13px] font-black"
                            :class="tone.box"
                            :style="{ color: tone.value }"
                            @click="detail = 'history'"
                        >
                            Histórico
                            <span :class="tone.muted">{{
                                specimen.history.length
                            }}</span>
                        </button>
                        <button
                            type="button"
                            class="flex h-11 cursor-pointer items-center justify-center gap-1.5 rounded-full text-[13px] font-black"
                            :class="[
                                tone.box,
                                light && specimen.history?.length
                                    ? ''
                                    : 'col-span-2',
                            ]"
                            :style="{ color: tone.value }"
                            @click="detail = 'hash'"
                        >
                            Hash
                            <span
                                class="rounded-full px-2 py-0.5 text-[10.5px] font-extrabold"
                                :class="tone.verified"
                                >✓ Verificado</span
                            >
                        </button>
                    </div>
                </template>
                <template v-else>
                    <SpecimenDetails
                        v-if="light"
                        part="history"
                        :specimen="specimen"
                        :tone="tone"
                        :history-nickname="historyNickname"
                    />
                    <SpecimenDetails
                        part="hash"
                        :specimen="specimen"
                        :tone="tone"
                        :history-nickname="historyNickname"
                    />
                </template>
                <slot name="actions" :specimen="specimen" />
            </div>
        </div>
    </PackModal>

    <!-- Fora do painel: ele inclina (transform), e um fixed dentro dele
         deixaria de cobrir a tela. -->
    <PackModal
        :open="specimen !== null && detail !== null"
        :label="detail === 'history' ? 'Histórico' : 'Hash do espécime'"
        max-width="420px"
        :light="light"
        @close="detail = null"
    >
        <div
            v-if="specimen && detail"
            class="flex flex-col gap-3 px-5 pt-16 pb-5"
        >
            <SpecimenDetails
                :part="detail"
                :specimen="specimen"
                :tone="tone"
                :history-nickname="historyNickname"
            />
            <a
                v-if="detail === 'hash'"
                :href="verifyShow().url"
                class="text-center text-[12.5px] font-extrabold"
                :class="tone.muted"
                >Refazer a conta em Verificar</a
            >
        </div>
    </PackModal>
</template>
