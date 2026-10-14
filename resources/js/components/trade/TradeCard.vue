<script setup lang="ts">
import { computed } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import { formatMint } from '@/lib/format';
import { rarityLook } from '@/lib/pack/rarity';
import type { TradeSpecimen } from '@/lib/trade/types';

/**
 * Carta de um espécime nas trocas (mesa, caixa, cerimônia): moldura e
 * painel da raridade, sprite, nome e número de série. `sm` é a cartinha
 * da caixa; `lg` a da cerimônia.
 */
const props = withDefaults(
    defineProps<{
        specimen: TradeSpecimen;
        size?: 'sm' | 'md' | 'lg';
        selected?: boolean;
    }>(),
    { size: 'md', selected: false },
);
defineEmits<{ open: [] }>();

const look = computed(() =>
    rarityLook(props.specimen.rarityTier, props.specimen.isShiny),
);
const dims = computed(
    () =>
        ({
            sm: {
                box: 'w-[64px]',
                sprite: 44,
                panel: 'h-[52px]',
                text: 'text-[9.5px]',
            },
            md: {
                box: 'w-[96px]',
                sprite: 64,
                panel: 'h-[74px]',
                text: 'text-[11px]',
            },
            lg: {
                box: 'w-[132px]',
                sprite: 92,
                panel: 'h-[104px]',
                text: 'text-[13px]',
            },
        })[props.size],
);
</script>

<template>
    <button
        type="button"
        class="relative shrink-0 cursor-pointer rounded-[16px] p-[2px] text-left transition-transform duration-200 active:scale-[0.96]"
        :class="[
            dims.box,
            selected
                ? 'outline-brand outline outline-[3px] outline-offset-2'
                : '',
        ]"
        :style="{
            background: look.frame,
            backgroundSize: specimen.isShiny ? '300% 100%' : undefined,
            boxShadow: look.shadow,
        }"
        :aria-label="`${specimen.name} ${formatMint(specimen.mintNumber)}`"
        @click="$emit('open')"
    >
        <span
            class="flex flex-col overflow-hidden rounded-[14px]"
            :style="{ background: look.card.body }"
        >
            <span
                class="relative flex items-end justify-center"
                :class="dims.panel"
                :style="{ background: look.card.panel ?? look.panel }"
            >
                <FittedSprite
                    :src="`/storage/${specimen.spritePath}`"
                    :alt="specimen.name"
                    :size="dims.sprite"
                    :padding="4"
                />
            </span>
            <span class="flex flex-col items-center px-1 pt-1 pb-1.5">
                <span
                    class="w-full truncate text-center leading-tight font-black"
                    :class="dims.text"
                    :style="{ color: look.card.nameInk }"
                    >{{ specimen.name }}</span
                >
                <span
                    class="font-mono text-[9px] font-extrabold"
                    :style="{ color: look.card.mutedInk }"
                    >{{ formatMint(specimen.mintNumber) }}</span
                >
            </span>
        </span>
    </button>
</template>
