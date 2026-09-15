<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'muted';
        circular?: boolean;
        disabled?: boolean;
        tag?: 'button' | 'a' | 'span';
    }>(),
    {
        variant: 'primary',
        circular: false,
        disabled: false,
        tag: 'button',
    },
);

const variantClasses: Record<string, string> = {
    primary: 'bg-brand text-white shadow-sm hover:bg-brand/90',
    secondary:
        'bg-surface text-ink ring-1 ring-inset ring-ink/15 hover:bg-ink/5',
    muted: 'bg-base text-ink/40',
};
</script>

<template>
    <component
        :is="tag"
        class="cursor-pointer font-sans font-bold transition active:scale-[0.98] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-60"
        :class="[
            circular
                ? 'flex h-12 w-12 items-center justify-center rounded-full'
                : 'rounded-full px-6 py-3 text-sm',
            variantClasses[variant],
        ]"
        :disabled="tag === 'button' ? disabled : undefined"
        :aria-disabled="disabled || undefined"
    >
        <slot />
    </component>
</template>
