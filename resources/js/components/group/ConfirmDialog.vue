<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { animate, spring } from '@/lib/motion';

/**
 * Diálogo de confirmação do próprio app (nunca o `confirm()` do navegador).
 * Prende o foco dentro dele, fecha com Esc ou clicando fora e devolve o
 * foco pra quem abriu.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        confirmLabel: string;
        cancelLabel?: string;
        processing?: boolean;
        danger?: boolean;
    }>(),
    { cancelLabel: 'Cancelar', processing: false, danger: false },
);

const emit = defineEmits<{ confirm: []; cancel: [] }>();

// O SSR do Inertia não injeta teleports no <body>; o diálogo só existe
// depois de montar no navegador, senão a hidratação não bate.
const isMounted = ref(false);
onMounted(() => (isMounted.value = true));

const panel = ref<HTMLElement | null>(null);
const confirmButton = ref<HTMLButtonElement | null>(null);
let opener: HTMLElement | null = null;

function cancel() {
    if (!props.processing) {
        emit('cancel');
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        cancel();

        return;
    }

    if (event.key !== 'Tab' || !panel.value) {
        return;
    }

    const focusable = [
        ...panel.value.querySelectorAll<HTMLElement>('button:not([disabled])'),
    ];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            opener = document.activeElement as HTMLElement | null;
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            confirmButton.value?.focus();
            animate(
                panel.value,
                [
                    { transform: 'translateY(24px) scale(.94)', opacity: 0 },
                    { transform: 'none', opacity: 1 },
                ],
                520,
                { easing: spring(260, 20) },
            );
        } else {
            document.removeEventListener('keydown', onKeydown);
            opener?.focus();
        }
    },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-[rgba(28,31,38,0.45)] p-4 backdrop-blur-[3px] sm:items-center"
            @click.self="cancel"
        >
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-dialog-title"
                class="flex w-full max-w-[400px] flex-col gap-4 rounded-[28px] bg-white p-6 shadow-[0_2px_4px_rgba(43,47,54,0.05),0_22px_56px_rgba(43,47,54,0.28)]"
            >
                <h2
                    id="confirm-dialog-title"
                    class="text-ink text-[22px] leading-[1.15] font-black tracking-[-0.025em] text-balance"
                >
                    {{ title }}
                </h2>
                <div
                    class="text-soft text-[14.5px] leading-relaxed font-semibold"
                >
                    <slot />
                </div>
                <div
                    class="mt-1 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        :disabled="processing"
                        class="text-muted border-line-strong h-12 cursor-pointer rounded-full border-2 bg-white px-5 text-[15px] font-extrabold"
                        @click="cancel"
                    >
                        {{ cancelLabel }}
                    </button>
                    <button
                        ref="confirmButton"
                        type="button"
                        :disabled="processing"
                        class="disabled:bg-brand-disabled h-12 cursor-pointer rounded-full px-5 text-[15px] font-extrabold text-white transition-transform active:scale-[.97]"
                        :class="
                            danger
                                ? 'bg-brand-ink shadow-[0_10px_24px_rgba(192,52,47,0.35)]'
                                : 'bg-brand shadow-[0_10px_24px_rgba(242,77,77,0.42)]'
                        "
                        @click="emit('confirm')"
                    >
                        {{ processing ? 'Salvando…' : confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
