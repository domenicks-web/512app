<script setup lang="ts">
defineProps<{
    modelValue: boolean;
    label?: string;
}>();

defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>

<template>
    <label class="flex cursor-pointer items-center justify-between gap-4">
        <span v-if="label" class="text-ink font-sans text-sm font-semibold">{{
            label
        }}</span>

        <span
            class="relative inline-flex h-[26px] w-[46px] shrink-0 items-center rounded-full transition-colors"
            :class="modelValue ? 'bg-brand' : 'bg-base'"
        >
            <input
                type="checkbox"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                :checked="modelValue"
                @change="
                    $emit(
                        'update:modelValue',
                        ($event.target as HTMLInputElement).checked,
                    )
                "
            />
            <span
                class="inline-block h-[20px] w-[20px] transform rounded-full bg-white shadow transition-transform"
                :class="modelValue ? 'translate-x-[23px]' : 'translate-x-[3px]'"
            />
        </span>
    </label>
</template>
