<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'dark' | 'blush' | 'base';
    }>(),
    {
        variant: 'base',
    },
);

const variantClasses: Record<string, string> = {
    dark: 'bg-ink text-white',
    blush: 'bg-brand-blush text-brand',
    base: 'bg-base text-ink/70',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold"
        :class="variantClasses[variant]"
    >
        <slot />
    </span>
</template>
