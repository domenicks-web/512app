<script setup lang="ts">
import { computed } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import { formatMint } from '@/lib/format';
import { GOLD } from '@/lib/pack/meta';
import { rarityLook } from '@/lib/pack/rarity';
import type { TradeSpecimen } from '@/lib/trade/types';

/**
 * Carta de um espécime nas trocas (mesa, caixa, cerimônia): moldura e
 * painel da raridade, sprite, nome, selo da raridade e número de série.
 * `sm` é a cartinha da caixa; `lg` a da cerimônia. Com `details`, um
 * botãozinho no canto abre a ficha (no montador o toque na carta já
 * escolhe).
 */
const props = withDefaults(
    defineProps<{
        specimen: TradeSpecimen;
        size?: 'sm' | 'md' | 'lg';
        selected?: boolean;
        details?: boolean;
    }>(),
    { size: 'md', selected: false, details: false },
);
defineEmits<{ open: []; details: [] }>();

const look = computed(() =>
    rarityLook(props.specimen.rarityTier, props.specimen.isShiny),
);
/** Selo da raridade: na carta dourada o selo dourado sumiria, então inverte. */
const badgeStyle = computed(() =>
    look.value.card.foil === 'gold'
        ? { background: '#3b2a00', color: GOLD }
        : { background: look.value.badge, color: look.value.badgeInk },
);
const dims = computed(
    () =>
        ({
            sm: {
                box: 'w-[64px]',
                sprite: 44,
                panel: 'h-[52px]',
                text: 'text-[9.5px]',
                badge: 'h-[13px] px-1 text-[7px]',
            },
            md: {
                box: 'w-[96px]',
                sprite: 64,
                panel: 'h-[74px]',
                text: 'text-[11px]',
                badge: 'h-[15px] px-1.5 text-[8px]',
            },
            lg: {
                box: 'w-[132px]',
                sprite: 92,
                panel: 'h-[104px]',
                text: 'text-[13px]',
                badge: 'h-[18px] px-2 text-[9.5px]',
            },
        })[props.size],
);
</script>

<template>
    <div class="relative shrink-0" :class="dims.box">
        <button
            type="button"
            class="relative w-full cursor-pointer rounded-[16px] p-[2px] text-left transition-transform duration-200 active:scale-[0.96]"
            :class="[
                selected
                    ? 'outline-brand outline outline-[3px] outline-offset-2'
                    : '',
            ]"
            :style="{
                background: look.frame,
                backgroundSize: specimen.isShiny ? '300% 100%' : undefined,
                boxShadow: look.shadow,
            }"
            :aria-label="`${specimen.name}, ${look.label}, ${formatMint(specimen.mintNumber)}`"
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
                        class="my-0.5 flex items-center rounded-full leading-none font-black tracking-[0.06em] uppercase"
                        :class="dims.badge"
                        :style="badgeStyle"
                        >{{ look.label }}</span
                    >
                    <span
                        class="font-mono text-[9px] font-extrabold"
                        :style="{ color: look.card.mutedInk }"
                        >{{ formatMint(specimen.mintNumber) }}</span
                    >
                </span>
            </span>
        </button>
        <button
            v-if="details"
            type="button"
            class="text-ink absolute -top-1.5 -right-1.5 flex size-6 cursor-pointer items-center justify-center rounded-full bg-white text-[12px] font-black shadow-[0_2px_6px_rgba(28,32,38,0.25)]"
            :aria-label="`Ver a ficha de ${specimen.name}`"
            @click="$emit('details')"
        >
            i
        </button>
    </div>
</template>
