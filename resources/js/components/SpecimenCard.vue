<script setup lang="ts">
import AppChip from '@/components/AppChip.vue';

withDefaults(
    defineProps<{
        state?: 'empty' | 'filled';
        rare?: boolean;
        rarityLabel?: string;
        registrationNumber?: string;
        name?: string;
        potential?: number;
        sizeLabel?: string;
    }>(),
    {
        state: 'filled',
    },
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            class="relative aspect-3/4 overflow-hidden rounded-2xl"
            :class="[
                state === 'empty' ? 'bg-base' : 'bg-screen-dark',
                rare && 'ring-brand ring-3',
            ]"
        >
            <template v-if="state === 'filled'">
                <AppChip
                    v-if="rarityLabel"
                    variant="blush"
                    class="absolute top-2 left-2"
                >
                    {{ rarityLabel }}
                </AppChip>
                <AppChip
                    v-if="registrationNumber"
                    variant="dark"
                    class="absolute bottom-2 left-2"
                >
                    {{ registrationNumber }}
                </AppChip>
            </template>
        </div>

        <div v-if="state === 'filled' && name" class="px-0.5">
            <p class="text-ink font-sans text-sm font-bold">{{ name }}</p>
            <p v-if="potential || sizeLabel" class="text-ink/50 text-xs">
                <span v-if="potential">Potencial {{ potential }}</span>
                <span v-if="potential && sizeLabel"> · </span>
                <span v-if="sizeLabel">{{ sizeLabel }}</span>
            </p>
        </div>
    </div>
</template>
