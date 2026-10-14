<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Modal do painel (handoff: card 420px, raio 32, `popIn`). Fecha com Esc ou
 * clicando fora, prende o foco e devolve pra quem abriu. Só monta o
 * Teleport no navegador (o SSR do Inertia não injeta teleports).
 */
const props = defineProps<{
    open: boolean;
    labelledBy: string;
    busy?: boolean;
}>();
const emit = defineEmits<{ close: [] }>();

const isMounted = ref(false);
onMounted(() => (isMounted.value = true));

const panel = ref<HTMLElement | null>(null);
let opener: HTMLElement | null = null;

function close() {
    if (!props.busy) {
        emit('close');
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();

        return;
    }

    if (event.key !== 'Tab' || !panel.value) {
        return;
    }

    const focusable = [
        ...panel.value.querySelectorAll<HTMLElement>(
            'input, button:not([disabled])',
        ),
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
            panel.value
                ?.querySelector<HTMLElement>('input, button:not([disabled])')
                ?.focus();
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
        <Transition name="admin-dialog">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-[rgba(28,31,38,0.55)] p-4"
                @click.self="close"
            >
                <div
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="labelledBy"
                    class="admin-dialog-panel entry-light text-ink flex w-full max-w-[420px] flex-col items-center gap-[18px] rounded-[32px] bg-white px-7 pt-[30px] pb-[26px] text-center font-sans shadow-[0_30px_80px_rgba(28,31,38,0.35)]"
                >
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.admin-dialog-enter-active,
.admin-dialog-leave-active {
    transition: opacity 0.2s ease;
}

.admin-dialog-enter-from,
.admin-dialog-leave-to {
    opacity: 0;
}

.admin-dialog-enter-active .admin-dialog-panel {
    animation: pop-in 0.32s cubic-bezier(0.2, 1.4, 0.4, 1);
}

@keyframes pop-in {
    from {
        opacity: 0;
        transform: scale(0.92) translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .admin-dialog-enter-active .admin-dialog-panel {
        animation: none;
    }
}
</style>
