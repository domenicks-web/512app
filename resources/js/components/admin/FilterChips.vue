<script setup lang="ts" generic="T extends string">
/** Chips de filtro (36px) com contagem, ativo em ink. */
defineProps<{
    options: { value: T; label: string; count: number }[];
}>();

const model = defineModel<T>({ required: true });
</script>

<template>
    <div class="flex flex-wrap gap-2" role="radiogroup">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            class="h-9 cursor-pointer rounded-full px-3.5 text-[13.5px] font-extrabold transition-colors"
            :class="
                model === option.value
                    ? 'bg-ink text-white'
                    : 'text-ink bg-white shadow-[0_1px_2px_rgba(28,32,38,0.06),0_4px_10px_rgba(28,32,38,0.05)] hover:bg-[#fafafb]'
            "
            @click="model = option.value"
        >
            {{ option.label }}
            <span class="ml-1 opacity-60">{{ option.count }}</span>
        </button>
    </div>
</template>
