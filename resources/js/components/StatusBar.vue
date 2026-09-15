<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue';

withDefaults(
    defineProps<{
        time: string;
        isSelf?: boolean;
    }>(),
    {
        isSelf: false,
    },
);
</script>

<template>
    <div
        class="bg-screen-dark flex items-center justify-end gap-3 rounded-full px-4 py-2 text-white"
    >
        <span class="font-sans text-sm font-semibold">{{ time }}</span>

        <svg
            class="h-4 w-4 opacity-80"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M2 8.5c5.5-5.3 14.5-5.3 20 0M5.5 12c3.7-3.5 9.3-3.5 13 0M9 15.5c1.9-1.7 4.1-1.7 6 0"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />
            <circle cx="12" cy="19" r="1.2" fill="currentColor" />
        </svg>

        <svg
            class="h-4 w-4 opacity-80"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <rect
                x="2"
                y="8"
                width="18"
                height="8"
                rx="2"
                stroke="currentColor"
                stroke-width="1.8"
            />
            <rect x="4.5" y="10" width="11" height="4" fill="currentColor" />
            <path
                d="M22 10.5v3"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />
        </svg>

        <svg
            class="h-4 w-4 opacity-80"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M12 2v8"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />
            <path
                d="M6.3 6.3a8 8 0 1 0 11.4 0"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />
        </svg>

        <span class="rounded-full" :class="isSelf && 'ring-2 ring-white'">
            <BrandMark variant="icon" :size="24" />
        </span>
    </div>
</template>
