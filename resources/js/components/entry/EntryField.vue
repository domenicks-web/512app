<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { useDotField } from '@/composables/useDotField';
import { animate, spring } from '@/lib/motion';

const model = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        label: string;
        name: string;
        type?: string;
        autocomplete?: string;
        maxlength?: number;
        error?: string | null;
    }>(),
    {
        type: 'text',
        autocomplete: 'off',
        maxlength: undefined,
        error: null,
    },
);

const input = ref<HTMLInputElement | null>(null);
const errorPill = ref<HTMLElement | null>(null);
const field = useDotField();

function onInput(event: Event) {
    const target = event.target as HTMLInputElement;
    model.value = target.value;
    field.rippleFromInput(target);
}

watch(
    () => props.error,
    async (error, previous) => {
        if (!error || error === previous) {
            return;
        }

        await nextTick();
        animate(
            errorPill.value,
            [
                { transform: 'translateY(-8px) scale(.6)', opacity: 0 },
                { transform: 'none', opacity: 1 },
            ],
            520,
            { easing: spring(300, 14) },
        );
    },
);

defineExpose({ input });
</script>

<template>
    <label class="flex flex-col gap-2">
        <span class="text-muted text-[13.5px] font-extrabold">{{ label }}</span>
        <input
            ref="input"
            :value="model"
            :type="type"
            :name="name"
            :autocomplete="autocomplete"
            :maxlength="maxlength"
            spellcheck="false"
            class="entry-input"
            :class="{ 'entry-input--error': error }"
            @input="onInput"
        />
        <span
            v-if="error"
            ref="errorPill"
            role="alert"
            class="text-brand-ink self-start rounded-full bg-[rgba(242,77,77,0.12)] px-3 py-1.5 text-[12.5px] font-extrabold"
        >
            {{ error }}
        </span>
    </label>
</template>
