<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';

const { isDark, toggle } = useTheme();
</script>

<template>
    <button
        type="button"
        aria-label="Trocar tema claro/escuro"
        class="bg-ink/5 text-ink/70 hover:bg-ink/10 flex h-10 w-10 cursor-pointer items-center justify-center rounded-full transition active:scale-95"
        @click="toggle"
    >
        <svg
            v-if="isDark"
            viewBox="0 0 24 24"
            fill="none"
            class="h-5 w-5"
            aria-hidden="true"
        >
            <circle
                cx="12"
                cy="12"
                r="4.5"
                stroke="currentColor"
                stroke-width="1.75"
            />
            <path
                stroke="currentColor"
                stroke-width="1.75"
                stroke-linecap="round"
                d="M12 2.5v2M12 19.5v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2.5 12h2M19.5 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"
            />
        </svg>
        <svg
            v-else
            viewBox="0 0 24 24"
            fill="none"
            class="h-5 w-5"
            aria-hidden="true"
        >
            <path
                stroke="currentColor"
                stroke-width="1.75"
                stroke-linejoin="round"
                d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 0 0 11 11Z"
            />
        </svg>
    </button>
</template>
