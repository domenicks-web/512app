<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, provide, ref, watch } from 'vue';
import HomeDock from '@/components/entry/HomeDock.vue';
import type { DockKey } from '@/components/entry/dock';
import { waitForIntro } from '@/composables/useIntro';
import { animate, finished, spring } from '@/lib/motion';
import { groupNavigationKey } from '@/layouts/groupNavigation';

/**
 * Layout persistente da Home e das telas do grupo (dentro do `EntryLayout`): o dock
 * fica parado entre as visitas e só o conteúdo troca, deslizando na ordem
 * do dock (pra frente vai pra esquerda, pra trás vai pra direita). A
 * entrada em cascata da tela só toca na primeira vez, vindo da Home ou de
 * um carregamento; entre abas, o deslize basta.
 */
const SCREENS: Record<string, { order: number; dock?: DockKey }> = {
    Home: { order: -1, dock: 'home' },
    'collection/Show': { order: 0, dock: 'collection' },
    'packs/Index': { order: 1, dock: 'packs' },
    'ranking/Show': { order: 2, dock: 'ranking' },
    'trades/Index': { order: 3, dock: 'trades' },
    'friends/Index': { order: 4, dock: 'friends' },
    'feed/Show': { order: 5 },
    'settings/Show': { order: 6, dock: 'settings' },
    'profile/Edit': { order: 7, dock: 'settings' },
};

const page = usePage();
const active = computed(() => SCREENS[page.component]?.dock);

const direction = ref(1);
watch(
    () => page.component,
    (next, previous) => {
        direction.value =
            (SCREENS[next]?.order ?? 0) >= (SCREENS[previous]?.order ?? 0)
                ? 1
                : -1;
    },
    { flush: 'sync' },
);

/** A tela atual entrou por deslize (o layout já existia antes dela). */
const hasMounted = ref(false);
provide(groupNavigationKey, {
    isSlideNavigation: () => hasMounted.value,
});

const dock = ref<HTMLElement | null>(null);

onMounted(async () => {
    await waitForIntro();
    await nextTick();
    animate(
        dock.value,
        [
            { transform: 'translateY(28px)', opacity: 0 },
            { transform: 'none', opacity: 1 },
        ],
        800,
        { delay: 200, easing: spring(170, 16) },
    );
    hasMounted.value = true;
});

/**
 * A tela nova pode travar a thread ao montar (ex.: o perfil mede vários
 * sprites); se o deslize começasse junto, ele terminaria antes do primeiro
 * quadro desenhado e a troca pareceria um pisca. Por isso ela fica
 * invisível até o navegador desenhar, e só então desliza.
 */
function onEnter(element: Element, done: () => void) {
    const target = element as HTMLElement;
    target.style.opacity = '0';

    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            target.style.opacity = '';
            void finished(
                animate(
                    target,
                    [
                        {
                            transform: `translateX(${direction.value * 28}%)`,
                            opacity: 0,
                        },
                        { transform: 'none', opacity: 1 },
                    ],
                    520,
                    { delay: 40, easing: spring(200, 24) },
                ),
            ).then(done);
        }),
    );
}

function onLeave(element: Element, done: () => void) {
    void finished(
        animate(
            element,
            [
                { transform: 'none', opacity: 1 },
                {
                    transform: `translateX(${direction.value * -16}%)`,
                    opacity: 0,
                },
            ],
            150,
            { easing: 'cubic-bezier(.4,0,1,1)', fill: 'forwards' },
        ),
    ).then(done);
}
</script>

<template>
    <div class="absolute inset-0 z-[3] flex flex-col">
        <div class="relative min-h-0 flex-1 overflow-hidden">
            <Transition :css="false" @enter="onEnter" @leave="onLeave">
                <div :key="page.component" class="absolute inset-0">
                    <slot />
                </div>
            </Transition>
        </div>

        <div class="flex justify-center px-4 pt-2 pb-[18px]">
            <div ref="dock">
                <HomeDock :active="active" />
            </div>
        </div>
    </div>
</template>
