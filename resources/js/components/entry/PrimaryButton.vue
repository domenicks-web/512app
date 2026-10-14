<script setup lang="ts">
import { ref } from 'vue';
import PokeballMark from '@/components/entry/PokeballMark.vue';

withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        size?: 'lg' | 'md';
        dimmed?: boolean;
    }>(),
    { type: 'button', size: 'md', dimmed: false },
);

// O `data-k="btok"` é a pokébola que nasce dentro do botão quando ele vira
// círculo no envio do login/cadastro (ver `useCaptureSubmit`).
const root = ref<HTMLButtonElement | null>(null);

defineExpose({ root });
</script>

<template>
    <button
        ref="root"
        :type="type"
        class="entry-button relative flex w-full cursor-pointer items-center justify-center overflow-hidden rounded-full p-0 font-extrabold text-white shadow-[0_10px_24px_rgba(242,77,77,0.42)] hover:-translate-y-0.5 hover:shadow-[0_14px_30px_rgba(242,77,77,0.42)] active:scale-[0.97]"
        :class="[
            size === 'lg'
                ? 'h-[58px] text-[17px] tracking-[-0.01em]'
                : 'h-[54px] text-[16px]',
            dimmed ? 'bg-brand-disabled' : 'bg-brand',
        ]"
    >
        <span data-k="blabel" class="flex items-center gap-2.5">
            <PokeballMark />
            <slot />
        </span>
        <span
            data-k="btok"
            class="absolute top-1/2 left-1/2 -mt-[15px] -ml-[15px] h-[30px] w-[30px] origin-[50%_90%] overflow-hidden rounded-full bg-white opacity-0 shadow-[0_0_0_2px_#fff]"
            aria-hidden="true"
        >
            <span class="bg-brand-cap absolute top-0 right-0 left-0 h-[15px]" />
            <span class="bg-steel absolute top-[13px] right-0 left-0 h-1" />
            <span
                class="bg-steel absolute top-1/2 left-1/2 -mt-[5.5px] -ml-[5.5px] flex h-[11px] w-[11px] items-center justify-center rounded-full"
            >
                <span class="bg-steel-light h-[5px] w-[5px] rounded-full" />
            </span>
        </span>
    </button>
</template>
