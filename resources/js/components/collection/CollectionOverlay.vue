<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Tela cheia por cima da página pra uma lista longa (a coleção no Cartão
 * de Treinador), pra que a página não vire um rolo infinito. Fecha no X,
 * no Esc e no voltar do celular: abrir empurra um passo no histórico, e o
 * voltar só consome esse passo. Fica abaixo da ficha (`z-50`), que abre
 * por cima dela.
 */
const props = defineProps<{ open: boolean; title: string; subtitle: string }>();
const emit = defineEmits<{ close: [] }>();
const panel = ref<HTMLElement | null>(null);
let pushedState = false;

function onKeydown(event: KeyboardEvent) {
    // Com a ficha aberta por cima, o Esc é dela.
    if (
        event.key === 'Escape' &&
        !document.querySelector('[role="dialog"][aria-modal="true"]')
    ) {
        emit('close');
    }
}

function onPopState() {
    pushedState = false;
    emit('close');
}

function teardown() {
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('popstate', onPopState);
    document.documentElement.style.overflow = '';
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            history.pushState({ ...history.state, overlay: true }, '');
            pushedState = true;
            document.addEventListener('keydown', onKeydown);
            window.addEventListener('popstate', onPopState);
            document.documentElement.style.overflow = 'hidden';
            await nextTick();
            panel.value?.animate(
                [
                    { opacity: 0, transform: 'translateY(40px)' },
                    { opacity: 1, transform: 'none' },
                ],
                { duration: 380, easing: 'cubic-bezier(.2,1.1,.4,1)' },
            );
            panel.value?.scrollTo({ top: 0 });
        } else {
            teardown();

            // Fechou pelo X ou Esc: desfaz o passo que a abertura empurrou.
            if (pushedState) {
                pushedState = false;
                history.back();
            }
        }
    },
);

onBeforeUnmount(teardown);
</script>

<template>
    <div
        v-if="open"
        ref="panel"
        class="entry-light fixed inset-0 z-40 overflow-x-hidden overflow-y-auto overscroll-contain bg-[#eff0f2]"
    >
        <header
            class="sticky top-0 z-[1] flex items-center gap-3 bg-[#eff0f2]/90 px-4 pt-4 pb-3 backdrop-blur-md sm:px-6"
        >
            <button
                type="button"
                aria-label="Fechar"
                class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white text-[#2b2f36] shadow-[0_2px_6px_rgba(28,32,38,0.12)] transition-transform active:scale-95"
                @click="emit('close')"
            >
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.6"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
            <div class="flex min-w-0 flex-col">
                <h2
                    class="truncate text-[20px] leading-tight font-black text-[#2b2f36]"
                >
                    {{ title }}
                </h2>
                <span class="text-sm font-bold text-[#7d8794]">{{
                    subtitle
                }}</span>
            </div>
        </header>
        <div class="mx-auto w-full max-w-[720px] px-4 pt-1 pb-10 sm:px-6">
            <slot />
        </div>
    </div>
</template>
