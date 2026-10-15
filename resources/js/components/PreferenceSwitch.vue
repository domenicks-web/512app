<script setup lang="ts">
/**
 * Linha com título, dica e interruptor, pras preferências do aparelho
 * (som, mexer as cartas) em Ajustes e no cadastro. Cores fixas, não
 * token: aparece dentro e fora do tema claro.
 */
defineProps<{
    title: string;
    hint: string;
    checked: boolean;
    disabled?: boolean;
}>();
const emit = defineEmits<{ toggle: [] }>();
</script>

<template>
    <label
        class="flex items-center gap-3.5"
        :class="disabled ? 'cursor-not-allowed opacity-55' : 'cursor-pointer'"
    >
        <div class="flex min-w-0 flex-1 flex-col gap-px">
            <span class="text-[15.5px] font-black text-[#2b2f36]">{{
                title
            }}</span>
            <span class="text-xs font-semibold text-[#7d8794]">{{ hint }}</span>
        </div>
        <span
            class="relative inline-flex h-[28px] w-[48px] shrink-0 items-center rounded-full transition-colors has-[:focus-visible]:shadow-[0_0_0_3px_rgba(242,77,77,0.35)]"
            :class="checked ? 'bg-[#F24D4D]' : 'bg-[#dfe2e6]'"
        >
            <input
                type="checkbox"
                role="switch"
                class="absolute inset-0 size-full cursor-pointer opacity-0"
                :checked="checked"
                :aria-checked="checked"
                :aria-label="title"
                :disabled="disabled"
                @change="emit('toggle')"
            />
            <span
                class="pointer-events-none block size-[22px] rounded-full bg-white shadow-[0_1px_3px_rgba(28,32,38,0.25)] transition-transform"
                :class="checked ? 'translate-x-[23px]' : 'translate-x-[3px]'"
            />
        </span>
    </label>
</template>
