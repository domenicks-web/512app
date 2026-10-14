<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { waitForIntro } from '@/composables/useIntro';

/**
 * Aviso curto do jogo (flash `toast` do servidor, ex.: "Email confirmado!").
 * Se a abertura estiver tocando, espera ela acabar pra aparecer. Fica no
 * topo, com quebra de linha, e some sozinho.
 */
const VISIBLE_MS = 3200;

const page = usePage();
const message = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let isReady = false;
let pending: string | null = null;

function show(text: string) {
    message.value = text;
    clearTimeout(timer);
    timer = setTimeout(() => (message.value = null), VISIBLE_MS);
}

watch(
    () => page.flash.toast,
    (text) => {
        if (!text) {
            return;
        }

        if (isReady) {
            show(text);
        } else {
            pending = text;
        }
    },
    { immediate: true },
);

onMounted(async () => {
    const intro = await waitForIntro();
    // Deixa a página entrar antes do aviso descer.
    await new Promise((resolve) =>
        setTimeout(resolve, intro.played ? 900 : 200),
    );
    isReady = true;

    if (pending) {
        show(pending);
        pending = null;
    }
});

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Transition name="game-toast">
        <div
            v-if="message"
            role="status"
            class="bg-ink fixed top-4 left-1/2 z-[60] flex w-max max-w-[min(440px,calc(100vw-32px))] -translate-x-1/2 items-center gap-2.5 rounded-[22px] py-3 pr-5 pl-3 text-sm leading-snug font-extrabold text-white shadow-[0_12px_30px_rgba(28,31,38,0.3)]"
        >
            <span
                class="bg-online flex size-[22px] shrink-0 items-center justify-center rounded-full"
            >
                <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M5 12.5l4.5 4.5L19 7.5" />
                </svg>
            </span>
            {{ message }}
        </div>
    </Transition>
</template>

<style scoped>
.game-toast-enter-active,
.game-toast-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.35s cubic-bezier(0.2, 1.4, 0.4, 1);
}

.game-toast-enter-from,
.game-toast-leave-to {
    opacity: 0;
    transform: translate(-50%, -14px);
}

@media (prefers-reduced-motion: reduce) {
    .game-toast-enter-active,
    .game-toast-leave-active {
        transition: opacity 0.2s ease;
    }
}
</style>
