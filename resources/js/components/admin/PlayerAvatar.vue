<script setup lang="ts">
import FittedSprite from '@/components/FittedSprite.vue';
import AdminIcon from './AdminIcon.vue';

/**
 * Avatar redondo do jogador no painel: o sprite do parceiro (ou a inicial,
 * pra quem ainda não passou pelo onboarding) e o selo estrela de admin.
 */
const props = withDefaults(
    defineProps<{
        spritePath: string | null;
        name: string;
        size?: number;
        isAdmin?: boolean;
        badgeColor?: string;
        ring?: boolean;
    }>(),
    { size: 40, isAdmin: false, badgeColor: '#f24d4d', ring: false },
);

const badgeSize = Math.round(props.size * 0.42);
</script>

<template>
    <div
        class="relative shrink-0"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <div
            class="flex size-full items-center justify-center overflow-hidden rounded-full bg-white"
            :class="
                ring
                    ? 'shadow-[0_0_0_2px_#fff,0_1px_3px_rgba(28,32,38,0.12)]'
                    : 'shadow-[0_0_0_1px_rgba(28,32,38,0.08),0_2px_6px_rgba(28,32,38,0.08)]'
            "
        >
            <FittedSprite
                v-if="spritePath"
                :src="`/storage/${spritePath}`"
                :size="size"
                :padding="Math.round(size * 0.14)"
            />
            <span
                v-else
                class="text-soft font-black"
                :style="{ fontSize: `${Math.round(size * 0.4)}px` }"
                >{{ name.charAt(0).toUpperCase() }}</span
            >
        </div>
        <span
            v-if="isAdmin"
            class="absolute -right-1 -bottom-1 flex items-center justify-center rounded-full text-white shadow-[0_0_0_2px_#fff]"
            :style="{
                width: `${badgeSize}px`,
                height: `${badgeSize}px`,
                background: badgeColor,
            }"
        >
            <AdminIcon name="star" :size="Math.round(badgeSize * 0.56)" />
        </span>
    </div>
</template>
