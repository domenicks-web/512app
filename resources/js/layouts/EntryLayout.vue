<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import GameToast from '@/components/entry/GameToast.vue';
import IntroLoader from '@/components/entry/IntroLoader.vue';
import { mountDotField } from '@/composables/useDotField';
import {
    introFlightTarget,
    introWillPlay,
    markIntroFinished,
    releasePageAfterIntro,
} from '@/composables/useIntro';

// Layout persistente do fluxo de entrada: o canvas do campo de pontos e a
// abertura vivem aqui e sobrevivem às visitas entre login, cadastro,
// onboarding e home.
const FLIGHT_DURATION = 780;

const canvas = ref<HTMLCanvasElement | null>(null);
const loader = ref<InstanceType<typeof IntroLoader> | null>(null);
const showIntro = ref(false);
// Começa escondido no servidor e no cliente (sem mismatch de hidratação);
// o cliente revela assim que sabe se a abertura vai tocar.
const isContentHidden = ref(true);
let unmount: (() => void) | null = null;

onMounted(async () => {
    if (canvas.value) {
        unmount = mountDotField(canvas.value);
    }

    if (!introWillPlay()) {
        isContentHidden.value = false;

        return;
    }

    showIntro.value = true;
    await nextTick();
    await loader.value?.play();

    // A página começa a própria entrada junto com o voo/sumiço do logo.
    isContentHidden.value = false;
    releasePageAfterIntro();
    const target = introFlightTarget();

    if (target) {
        await loader.value?.flyTo(target, FLIGHT_DURATION);
    } else {
        await loader.value?.vanish();
    }

    markIntroFinished();
    showIntro.value = false;
});

onBeforeUnmount(() => {
    loader.value?.cancel();
    unmount?.();
});
</script>

<template>
    <div
        class="entry-light bg-base text-ink relative h-dvh min-h-[600px] w-full overflow-hidden font-sans antialiased"
    >
        <canvas
            ref="canvas"
            class="pointer-events-none fixed inset-0 z-[1] h-full w-full"
            aria-hidden="true"
        />
        <IntroLoader v-if="showIntro" ref="loader" />
        <div :class="{ invisible: isContentHidden }">
            <slot />
        </div>
        <GameToast />
    </div>
</template>
