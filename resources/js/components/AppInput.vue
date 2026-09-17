<script setup lang="ts">
const model = defineModel<string>({ default: '' });

withDefaults(
    defineProps<{
        label: string;
        name: string;
        type?: string;
        autocomplete?: string;
        required?: boolean;
        error?: string;
    }>(),
    {
        type: 'text',
        autocomplete: 'off',
        required: false,
        error: undefined,
    },
);
</script>

<template>
    <label class="flex flex-col gap-1.5 text-left">
        <span class="text-ink/70 text-sm font-bold">{{ label }}</span>
        <input
            v-model="model"
            :type="type"
            :name="name"
            :autocomplete="autocomplete"
            :required="required"
            class="border-ink/15 bg-ink/5 text-ink placeholder:text-ink/30 focus:border-brand focus:ring-brand/30 rounded-full border px-5 py-2.5 text-sm outline-none focus:ring-2"
            :class="error ? 'border-red-400' : ''"
        />
        <span v-if="error" class="text-xs font-semibold text-red-500">{{
            error
        }}</span>
    </label>
</template>
