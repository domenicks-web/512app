<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'icon' | 'lockup';
        size?: number;
    }>(),
    {
        variant: 'lockup',
        size: 40,
    },
);
</script>

<template>
    <img
        v-if="variant === 'icon'"
        src="/favicon.svg"
        alt="512"
        :style="{ width: `${size}px`, height: `${size}px` }"
        class="shrink-0"
    />

    <img v-else src="/logo.svg" alt="512" class="h-12 w-auto" />
</template>
