<script setup lang="ts">
import { computed } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import { vCardTilt } from '@/lib/cardTilt';
import { GOLD, pad } from '@/lib/pack/meta';
import { rarityLook } from '@/lib/pack/rarity';
import type { Tier } from '@/lib/pack/types';

/**
 * Carta da coleção, no tratamento das cartas reais: comum fosca, incomum
 * chapada, raro holo, lendário toda dourada e shiny "full art" escura com
 * foil arco-íris. Moldura engrossa com a raridade, o #0001 leva o selo de
 * primeira edição e, no desktop, raro pra cima inclina com o mouse
 * (`v-card-tilt`). Sem espécime: silhueta e "???".
 */
const props = defineProps<{
    entry: {
        id: number;
        name: string;
        rarityTier: string;
        spritePath: string;
        spriteShinyPath: string;
        specimenCount: number;
        shinyCount: number;
        bestMint: number | null;
        bestIv: number | null;
        fromTrade?: boolean;
    };
    loading?: boolean;
}>();

defineEmits<{ open: [] }>();

const owned = computed(() => props.entry.specimenCount > 0);
const shiny = computed(() => props.entry.shinyCount > 0);
const look = computed(() =>
    rarityLook(props.entry.rarityTier as Tier, shiny.value),
);
const dex = computed(() => `#${pad(props.entry.id, 3)}`);
const skin = computed(() => look.value.card);
const isFirst = computed(() => props.entry.bestMint === 1);

/** Selo e mint de destaque precisam contrastar com o fundo da carta. */
const badgeStyle = computed(() =>
    skin.value.foil === 'gold'
        ? { background: '#3b2a00', color: GOLD }
        : { background: look.value.badge, color: look.value.badgeInk },
);
const mintHighlight = computed(() =>
    skin.value.foil === 'gold'
        ? '#3b2a00'
        : skin.value.foil === 'rainbow'
          ? GOLD
          : '#a87700',
);
</script>

<template>
    <button
        v-if="owned"
        v-card-tilt="skin.tilt"
        type="button"
        class="group relative w-full cursor-pointer rounded-[20px] text-left transition-transform duration-200 hover:-translate-y-1 active:scale-[0.97]"
        :class="[
            shiny ? 'motion-safe:animate-foil-drift' : '',
            loading ? 'opacity-60' : '',
        ]"
        :style="{
            padding: `${skin.frameWidth}px`,
            background: look.frame,
            backgroundSize: shiny ? '300% 100%' : undefined,
            boxShadow: look.shadow,
        }"
        :aria-label="`Ver ${entry.name}`"
        @click="$emit('open')"
    >
        <div
            class="relative flex flex-col overflow-hidden"
            :style="{
                borderRadius: `${20 - skin.frameWidth}px`,
                background: skin.body,
            }"
        >
            <div
                v-if="skin.foil === 'rainbow'"
                class="card-foil card-foil-rainbow card-foil-interactive opacity-25"
            />
            <div
                class="relative flex h-[94px] items-end justify-center overflow-hidden"
                :style="{ background: skin.panel ?? look.panel }"
            >
                <div
                    v-if="skin.foil === 'holo'"
                    class="card-foil card-foil-holo card-foil-interactive"
                />
                <div
                    v-if="skin.foil === 'holo' || skin.foil === 'gold'"
                    class="card-foil card-foil-stars card-foil-interactive"
                />
                <div
                    v-if="skin.foil === 'rainbow'"
                    class="card-foil card-foil-rainbow card-foil-interactive opacity-60"
                />
                <span
                    class="absolute top-2 left-2.5 text-[10px] font-black tracking-[0.04em]"
                    :style="{ color: shiny ? '#9eeef6' : look.ink }"
                    >{{ dex }}</span
                >
                <span
                    class="absolute top-2.5 right-2.5 flex gap-[3px]"
                    :aria-label="look.label"
                >
                    <span
                        v-for="gem in look.gems"
                        :key="gem"
                        class="size-[7px] rotate-45 rounded-[1.5px] shadow-[0_0_0_1.5px_#ffffff]"
                        :style="{ background: look.badge }"
                    />
                </span>
                <span
                    v-if="entry.fromTrade"
                    class="absolute top-[26px] right-2.5 rounded-full bg-white/90 px-1.5 text-[10px] leading-[16px] font-black text-[#2b2f36] shadow-[0_1px_2px_rgba(0,0,0,0.15)]"
                    title="Veio de troca"
                    aria-label="Veio de troca"
                    >⇄</span
                >
                <div
                    class="absolute bottom-2 left-1/2 h-2.5 w-14 -translate-x-1/2 rounded-[50%] blur-[3px]"
                    :class="shiny ? 'bg-black/40' : 'bg-black/15'"
                />
                <FittedSprite
                    :src="`/storage/${shiny ? entry.spriteShinyPath : entry.spritePath}`"
                    :alt="entry.name"
                    :size="80"
                    :padding="5"
                    class="relative mb-1 transition-transform duration-300 group-hover:scale-110"
                />
                <template v-if="shiny">
                    <span
                        v-for="spark in 3"
                        :key="spark"
                        class="card-sparkle"
                        :class="`card-sparkle-${spark}`"
                        aria-hidden="true"
                        >✦</span
                    >
                </template>
                <span
                    v-if="isFirst"
                    class="absolute bottom-1.5 left-2 flex size-[27px] -rotate-12 flex-col items-center justify-center rounded-full border-[1.5px] leading-none font-black shadow-[0_1px_3px_rgba(0,0,0,0.25)]"
                    :class="
                        shiny
                            ? 'border-white bg-black/45 text-white'
                            : 'border-[#2b2f36] bg-white/90 text-[#2b2f36]'
                    "
                    title="Primeiro do servidor"
                    aria-label="Primeiro do servidor"
                >
                    <span class="text-[6px] tracking-[0.06em]">Nº</span>
                    <span class="-mt-px text-[12px]">1</span>
                </span>
                <div
                    v-if="look.sheen"
                    class="motion-safe:animate-foil-sweep pointer-events-none absolute inset-0 bg-[linear-gradient(115deg,rgba(255,255,255,0)_38%,rgba(255,255,255,0.7)_50%,rgba(255,255,255,0)_62%)] bg-[length:250%_100%] bg-[position:160%_0] mix-blend-overlay"
                />
            </div>

            <div
                class="relative flex flex-col items-center gap-1.5 px-2 pt-2 pb-2.5"
            >
                <span
                    class="w-full truncate text-center text-[13px] leading-tight font-black"
                    :style="{ color: skin.nameInk }"
                    >{{ entry.name }}</span
                >
                <span
                    class="flex h-[18px] items-center rounded-full px-2 text-[9.5px] font-black tracking-[0.08em] uppercase"
                    :style="badgeStyle"
                    >{{ look.label }}</span
                >
                <div
                    class="flex w-full items-center justify-between gap-1 pt-0.5 text-[10px] font-extrabold"
                    :style="{ color: skin.mutedInk }"
                >
                    <span
                        class="font-mono"
                        :style="
                            entry.bestMint !== null && entry.bestMint <= 10
                                ? { color: mintHighlight }
                                : undefined
                        "
                        >Nº {{ pad(entry.bestMint ?? 0, 4) }}</span
                    >
                    <span>IV {{ entry.bestIv }}</span>
                </div>
            </div>

            <div
                v-if="skin.tilt"
                class="card-glare"
                :class="shiny ? 'mix-blend-screen' : 'mix-blend-overlay'"
            />
        </div>

        <span
            v-if="entry.specimenCount > 1"
            class="absolute -top-1.5 -right-1.5 rounded-full bg-[#2b2f36] px-2 py-0.5 text-[11px] font-black text-white shadow-[0_0_0_2px_#ffffff]"
            >×{{ entry.specimenCount }}</span
        >
    </button>

    <div
        v-else
        class="relative flex w-full flex-col items-center rounded-[20px] bg-white/45 px-2 pt-2 pb-3"
    >
        <span
            class="absolute top-2 left-2.5 text-[10px] font-black tracking-[0.04em] text-[#9aa0a8]"
            >{{ dex }}</span
        >
        <FittedSprite
            :src="`/storage/${entry.spritePath}`"
            alt=""
            :size="72"
            :padding="6"
            class="mt-5 opacity-[0.13] brightness-0"
        />
        <span
            class="text-placeholder mt-1 w-full truncate text-center text-[12.5px] font-extrabold"
            >???</span
        >
    </div>
</template>
