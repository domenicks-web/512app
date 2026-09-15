<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        value: number;
        max?: number;
    }>(),
    {
        max: 100,
    },
);

const percentage = () =>
    Math.min(100, Math.max(0, (props.value / props.max) * 100));
</script>

<template>
    <div
        class="bg-base h-1.5 w-full overflow-hidden rounded-full"
        role="progressbar"
        :aria-valuenow="value"
        aria-valuemin="0"
        :aria-valuemax="max"
    >
        <div
            class="bg-brand h-full rounded-full transition-[width]"
            :style="{ width: `${percentage()}%` }"
        />
    </div>
</template>
