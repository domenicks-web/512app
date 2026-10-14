<script setup lang="ts">
import { computed } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import { pad } from '@/lib/pack/meta';
import { rarityLook } from '@/lib/pack/rarity';
import type { Tier } from '@/lib/pack/types';

/**
 * Carta da coleção. Com espécime: moldura metálica da raridade, fundo com
 * brilho e padrão, reflexo correndo nas raras, gemas, selo, melhor mint e
 * maior IV. Sem espécime: silhueta e "???".
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
</script>

<template>
    <button
        v-if="owned"
        type="button"
        class="group relative w-full cursor-pointer rounded-[20px] p-[2.5px] text-left transition-transform duration-200 hover:-translate-y-1 active:scale-[0.97]"
        :class="[
            shiny ? 'motion-safe:animate-foil-drift' : '',
            loading ? 'opacity-60' : '',
        ]"
        :style="{
            background: look.frame,
            backgroundSize: shiny ? '300% 100%' : undefined,
            boxShadow: look.shadow,
        }"
        :aria-label="`Ver ${entry.name}`"
        @click="$emit('open')"
    >
        <div
            class="relative flex flex-col overflow-hidden rounded-[17.5px] bg-white"
        >
            <div
                class="relative flex h-[94px] items-end justify-center overflow-hidden"
                :style="{ background: look.panel }"
            >
                <span
                    class="absolute top-2 left-2.5 text-[10px] font-black tracking-[0.04em]"
                    :style="{ color: look.ink }"
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
                <div
                    class="absolute bottom-2 left-1/2 h-2.5 w-14 -translate-x-1/2 rounded-[50%] bg-black/15 blur-[3px]"
                />
                <FittedSprite
                    :src="`/storage/${shiny ? entry.spriteShinyPath : entry.spritePath}`"
                    :alt="entry.name"
                    :size="80"
                    :padding="5"
                    class="relative mb-1 transition-transform duration-300 group-hover:scale-110"
                />
                <div
                    v-if="look.sheen"
                    class="motion-safe:animate-foil-sweep pointer-events-none absolute inset-0 bg-[linear-gradient(115deg,rgba(255,255,255,0)_38%,rgba(255,255,255,0.7)_50%,rgba(255,255,255,0)_62%)] bg-[length:250%_100%] bg-[position:160%_0] mix-blend-overlay"
                />
            </div>

            <div class="flex flex-col items-center gap-1.5 px-2 pt-2 pb-2.5">
                <span
                    class="w-full truncate text-center text-[13px] leading-tight font-black text-[#2b2f36]"
                    >{{ entry.name }}</span
                >
                <span
                    class="flex h-[18px] items-center rounded-full px-2 text-[9.5px] font-black tracking-[0.08em] uppercase"
                    :style="{ background: look.badge, color: look.badgeInk }"
                    >{{ look.label }}</span
                >
                <div
                    class="flex w-full items-center justify-between gap-1 pt-0.5 text-[10px] font-extrabold text-[#8a9099]"
                >
                    <span
                        class="font-mono"
                        :class="
                            entry.bestMint !== null && entry.bestMint <= 10
                                ? 'text-[#a87700]'
                                : ''
                        "
                        >Nº {{ pad(entry.bestMint ?? 0, 4) }}</span
                    >
                    <span>IV {{ entry.bestIv }}</span>
                </div>
            </div>
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
