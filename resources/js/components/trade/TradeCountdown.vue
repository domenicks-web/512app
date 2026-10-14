<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Validade da proposta: "vence em 2d 14h" com a barra esvaziando; nas
 * últimas 24h vira hh:mm:ss em vermelho. Só conta o `expiresAt` que veio
 * do servidor.
 */
const props = defineProps<{ expiresAt: string; totalHours: number }>();

const now = ref(Date.now());
let clock = 0;
onMounted(
    () => (clock = window.setInterval(() => (now.value = Date.now()), 1000)),
);
onBeforeUnmount(() => clearInterval(clock));

const remaining = computed(() =>
    Math.max(0, new Date(props.expiresAt).getTime() - now.value),
);
const urgent = computed(() => remaining.value < 24 * 3600 * 1000);
const fraction = computed(() =>
    Math.min(1, remaining.value / (props.totalHours * 3600 * 1000)),
);
const label = computed(() => {
    const seconds = Math.floor(remaining.value / 1000);
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const pad = (n: number) => String(n).padStart(2, '0');

    if (remaining.value === 0) {
        return 'venceu';
    }

    return urgent.value
        ? `vence em ${pad(hours)}:${pad(minutes)}:${pad(seconds % 60)}`
        : `vence em ${days}d ${hours}h`;
});
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="bg-track h-1.5 w-16 overflow-hidden rounded-full">
            <div
                class="h-full rounded-full transition-[width] duration-1000"
                :class="urgent ? 'bg-brand' : 'bg-[#2b2f36]'"
                :style="{ width: `${fraction * 100}%` }"
            />
        </div>
        <span
            class="text-[11.5px] font-extrabold tabular-nums"
            :class="urgent ? 'text-brand-ink' : 'text-soft'"
            >{{ label }}</span
        >
    </div>
</template>
