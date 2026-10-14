<script setup lang="ts">
import FittedSprite from '@/components/FittedSprite.vue';
/** Avatar redondo do treinador com o sprite do parceiro (ou vazio). */
withDefaults(
    defineProps<{
        spritePath: string | null;
        size?: 'sm' | 'md';
        highlighted?: boolean;
    }>(),
    { size: 'md', highlighted: false },
);
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-white"
        :class="[
            size === 'sm' ? 'size-10' : 'size-12',
            highlighted
                ? 'shadow-[0_0_0_3px_#fff,0_0_0_5px_#f24d4d,0_6px_14px_rgba(28,32,38,0.12)]'
                : 'shadow-[0_0_0_1px_rgba(28,32,38,0.06),0_4px_10px_rgba(28,32,38,0.08)]',
        ]"
    >
        <FittedSprite
            v-if="spritePath"
            :src="`/storage/${spritePath}`"
            :size="size === 'sm' ? 40 : 48"
            :padding="size === 'sm' ? 6 : 7"
        />
    </div>
</template>
