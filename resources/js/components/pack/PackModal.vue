<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/** Moldura dos modais escuros da abertura (ficha e comprovante). Fecha com Esc ou clicando fora. */
const props = defineProps<{ open: boolean; label: string; maxWidth: string }>();
const emit = defineEmits<{ close: [] }>();
const panel = ref<HTMLElement | null>(null);

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            panel.value?.animate(
                [
                    { opacity: 0, transform: 'translateY(30px) scale(.96)' },
                    { opacity: 1, transform: 'none' },
                ],
                { duration: 420, easing: 'cubic-bezier(.2,1.3,.4,1)' },
            );
            panel.value?.querySelector<HTMLElement>('button')?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="open"
        class="absolute inset-0 z-30 box-border flex items-center justify-center overflow-x-hidden overflow-y-auto bg-[rgba(28,31,38,0.62)] p-3 sm:p-5"
        @click.self="emit('close')"
    >
        <div
            ref="panel"
            role="dialog"
            aria-modal="true"
            :aria-label="label"
            class="relative m-auto w-full overflow-hidden rounded-[26px] bg-[#1c1f26] text-white shadow-[0_40px_90px_rgba(0,0,0,0.45)] sm:rounded-[32px]"
            :style="{ maxWidth }"
        >
            <button
                type="button"
                aria-label="Fechar"
                class="absolute top-3.5 right-3.5 z-[2] flex size-[38px] cursor-pointer items-center justify-center rounded-full bg-white/8 text-[#c3cad4]"
                @click="emit('close')"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
            <slot />
        </div>
    </div>
</template>
