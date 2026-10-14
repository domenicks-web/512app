<script setup lang="ts">
/** Ícones de traço do painel (copiar, revogar, check, lupa, sortear, mais). */
defineProps<{
    name: 'copy' | 'revoke' | 'check' | 'search' | 'reroll' | 'more' | 'star';
    size?: number;
}>();
</script>

<template>
    <svg
        :width="size ?? 16"
        :height="size ?? 16"
        viewBox="0 0 24 24"
        :fill="name === 'star' || name === 'more' ? 'currentColor' : 'none'"
        stroke="currentColor"
        :stroke-width="
            name === 'check'
                ? 3
                : name === 'search' || name === 'reroll'
                  ? 2.6
                  : 2.4
        "
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="shrink-0"
    >
        <template v-if="name === 'copy'">
            <rect x="8" y="8" width="12" height="12" rx="3" />
            <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
        </template>
        <template v-else-if="name === 'revoke'">
            <circle cx="12" cy="12" r="8.5" />
            <path d="M6 18L18 6" />
        </template>
        <path v-else-if="name === 'check'" d="M5 12.5l4.5 4.5L19 7.5" />
        <template v-else-if="name === 'search'">
            <circle cx="11" cy="11" r="6.5" />
            <path d="M16 16l4 4" />
        </template>
        <template v-else-if="name === 'reroll'">
            <path d="M4 12a8 8 0 1 0 2.4-5.7" />
            <path d="M4 4v4h4" />
        </template>
        <template v-else-if="name === 'more'">
            <circle cx="5" cy="12" r="1.8" stroke="none" />
            <circle cx="12" cy="12" r="1.8" stroke="none" />
            <circle cx="19" cy="12" r="1.8" stroke="none" />
        </template>
        <path
            v-else-if="name === 'star'"
            stroke="none"
            d="M12 2l2.9 6.6 7.1.6-5.4 4.7 1.6 7L12 17.3 5.8 20.9l1.6-7L2 9.2l7.1-.6z"
        />
    </svg>
</template>
