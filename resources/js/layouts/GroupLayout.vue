<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    watch,
} from 'vue';
import HomeDock from '@/components/entry/HomeDock.vue';
import DockBubble from '@/components/group/DockBubble.vue';
import FriendsOnline from '@/components/group/FriendsOnline.vue';
import type { DockKey } from '@/components/entry/dock';
import { waitForIntro } from '@/composables/useIntro';
import { useRealtime } from '@/composables/useRealtime';
import { animate, finished, prefersReducedMotion, spring } from '@/lib/motion';
import { Sfx } from '@/lib/pack/sfx';
import type { Notice } from '@/lib/realtime/notice';
import { isSoundMuted } from '@/lib/soundPreference';
import { groupNavigationKey } from '@/layouts/groupNavigation';

/**
 * Layout persistente da Home e das telas do grupo (dentro do `EntryLayout`): o dock
 * fica parado entre as visitas e só o conteúdo troca, deslizando na ordem
 * do dock (pra frente vai pra esquerda, pra trás vai pra direita). A
 * entrada em cascata da tela só toca na primeira vez, vindo da Home ou de
 * um carregamento; entre abas, o deslize basta. Os avisos em tempo real
 * (`useRealtime`) saem como bolha do ícone certo do dock, um de cada vez.
 */
const SCREENS: Record<string, { order: number; dock?: DockKey }> = {
    Home: { order: -1, dock: 'home' },
    'collection/Show': { order: 0, dock: 'collection' },
    'ranking/Show': { order: 2, dock: 'ranking' },
    'trades/Index': { order: 3, dock: 'trades' },
    'friends/Index': { order: 4, dock: 'friends' },
    'feed/Show': { order: 5 },
    'settings/Show': { order: 6, dock: 'settings' },
    'profile/Edit': { order: 7, dock: 'settings' },
    'verify/Show': { order: 8, dock: 'settings' },
    'trainer/Show': { order: 9 },
    'compare/Show': { order: 10 },
};

const page = usePage<{
    friendsOnline?: number;
    auth: { user: { id: number } | null };
}>();
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
const row = ref<HTMLElement | null>(null);

// Avisos em tempo real: um por vez; 3 ou mais do mesmo ícone esperando
// viram uma bolha só.
const { queue } = useRealtime(() => page.props.auth.user?.id ?? null);
const current = ref<{
    id: number;
    notice: Notice;
    anchorX: number;
    rowWidth: number;
}>();
let shown = 0;
const sfx = new Sfx();
const PLACE: Record<Notice['dock'], string> = {
    trades: 'nas trocas',
    friends: 'nos amigos',
    home: 'no feed',
};

function nextNotice() {
    const head = queue.value[0];

    if (current.value || !head || !row.value) {
        return;
    }

    const same = queue.value.filter((n) => n.dock === head.dock);
    let notice: Notice;

    if (same.length >= 3) {
        queue.value = queue.value.filter((n) => n.dock !== head.dock);
        notice = {
            ...head,
            title: `${same.length} novidades ${PLACE[head.dock]}`,
            body: null,
            spritePath: null,
            tier: null,
            isShiny: false,
        };
    } else {
        notice = queue.value.shift() as Notice;
    }

    const box = row.value.getBoundingClientRect();
    const icon = row.value.querySelector(`[data-dock="${notice.dock}"]`);
    const at = icon?.getBoundingClientRect();
    current.value = {
        id: ++shown,
        notice,
        anchorX: at ? at.left + at.width / 2 - box.left : box.width / 2,
        rowWidth: box.width,
    };

    if (icon && !prefersReducedMotion()) {
        animate(
            icon,
            [
                { transform: 'none' },
                { transform: 'translateY(-10px) scale(1.12)', offset: 0.35 },
                { transform: 'none' },
            ],
            620,
            { easing: spring(320, 13) },
        );
    }

    sfx.on = !isSoundMuted();
    sfx.notice(notice.kind, notice.tier, notice.isShiny);

    if (notice.kind === 'trade_received' || notice.kind === 'trade_accepted') {
        try {
            navigator.vibrate?.(20);
        } catch {
            // sem vibração
        }
    }
}

function onBubbleDone() {
    current.value = undefined;
    void nextTick(nextNotice);
}

watch(() => queue.value.length, nextNotice);

// O áudio só pode começar depois de um gesto do jogador.
const unlockAudio = () => sfx.init();
onMounted(() => window.addEventListener('pointerdown', unlockAudio));
onBeforeUnmount(() => {
    window.removeEventListener('pointerdown', unlockAudio);
    sfx.close();
});

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

        <div
            ref="row"
            class="relative flex justify-center px-[clamp(16px,5vw,56px)] pt-2 pb-[18px]"
        >
            <DockBubble
                v-if="current"
                :key="current.id"
                :notice="current.notice"
                :more="queue.length"
                :anchor-x="current.anchorX"
                :row-width="current.rowWidth"
                @done="onBubbleDone"
            />
            <!-- Na Home, os amigos online ficam na linha do dock, no canto
                 esquerdo (no celular não cabe: a Home mostra acima do dock). -->
            <Transition
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
                enter-active-class="transition-opacity duration-300"
                leave-active-class="transition-opacity duration-150"
            >
                <FriendsOnline
                    v-if="page.component === 'Home'"
                    :count="page.props.friendsOnline ?? 0"
                    class="absolute bottom-[42px] left-[clamp(24px,7vw,96px)] max-sm:hidden"
                />
            </Transition>
            <!-- No celular o dock ocupa a largura do conteúdo. -->
            <div ref="dock" class="max-sm:w-full">
                <HomeDock :active="active" />
            </div>
        </div>
    </div>
</template>
