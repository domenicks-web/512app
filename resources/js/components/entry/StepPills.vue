<script setup lang="ts">
import { ref } from 'vue';

defineProps<{
    count: number;
    current: number;
}>();

const root = ref<HTMLElement | null>(null);

defineExpose({ root });
</script>

<template>
    <div ref="root" class="flex gap-2" aria-hidden="true">
        <div
            v-for="index in count"
            :key="index"
            data-pill
            class="bg-track h-[7px] w-10 overflow-hidden rounded-full"
        >
            <div
                class="bg-brand h-full rounded-full transition-[width] duration-600 ease-[cubic-bezier(.34,1.56,.64,1)]"
                :style="{
                    width:
                        index - 1 < current
                            ? '100%'
                            : index - 1 === current
                              ? '34%'
                              : '0%',
                }"
            />
        </div>
    </div>
</template>
