<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { DockKey } from '@/components/entry/dock';
import { animate } from '@/lib/motion';
import { home, logout } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { edit as profileEdit } from '@/routes/profile';
import { show as packShow } from '@/routes/pack';
import { index as packsIndex } from '@/routes/packs';
import { show as collectionShow } from '@/routes/collection';
import { index as friendsIndex } from '@/routes/friends';
import { show as rankingShow } from '@/routes/ranking';
import { index as tradesIndex } from '@/routes/trades';
import type { RouteDefinition } from '@/wayfinder';

/**
 * Dock de vidro da home. Cada ícone tem uma micro-animação no hover
 * (cartas em leque, cápsula abre, barras sobem, setas trocam, mão acena,
 * sliders correm). A casa volta pra Home; a pokébola vermelha abre o pack
 * do dia e se enche de vermelho conforme ele fica disponível; Coleção,
 * Packs, Ranking, Trocas e Amigos navegam; "Ajustes" abre o menu.
 * `active` marca o item da tela atual. Amigos mostra um contador vermelho
 * com os pedidos de amizade recebidos.
 */
defineProps<{
    isAdmin: boolean;
    active?: DockKey;
}>();

type DockItem = {
    key: DockKey;
    label: string;
    href?: () => RouteDefinition<'get'>;
};

const items: DockItem[] = [
    { key: 'collection', label: 'Coleção', href: collectionShow },
    { key: 'packs', label: 'Packs', href: packsIndex },
    { key: 'ranking', label: 'Ranking', href: rankingShow },
    { key: 'trades', label: 'Trocas', href: tradesIndex },
    { key: 'friends', label: 'Amigos', href: friendsIndex },
    { key: 'settings', label: 'Ajustes' },
];

const MICRO: Record<string, string[]> = {
    cardBack: ['none', 'rotate(-16deg) translateX(-1px)', 'none'],
    cardFront: ['none', 'rotate(12deg) translateX(1px)', 'none'],
    capTop: ['none', 'translateY(-3px) rotate(-16deg)', 'none'],
    capBot: ['none', 'translateY(2px)', 'none'],
    b1: ['scaleY(1)', 'scaleY(1.45)', 'scaleY(.85)', 'scaleY(1)'],
    b2: ['scaleY(1)', 'scaleY(1.25)', 'scaleY(.9)', 'scaleY(1)'],
    b3: ['scaleY(1)', 'scaleY(1.5)', 'scaleY(.85)', 'scaleY(1)'],
    arR: ['none', 'translateX(3px)', 'none'],
    arL: ['none', 'translateX(-3px)', 'none'],
    wave: ['none', 'rotate(16deg)', 'rotate(-10deg)', 'none'],
    k1: ['none', 'translateX(6px)', 'none'],
    k2: ['none', 'translateX(-6px)', 'none'],
    roof: ['none', 'translateY(-2.5px)', 'none'],
};

const page = usePage<{
    pendingFriendRequests?: number;
    packStatus?: { nextPackAt: string | null; cooldownHours: number } | null;
}>();

/**
 * Quanto falta pro próximo pack, de 0 (acabou de abrir) a 1 (disponível):
 * vira a altura do vermelho que enche a pokébola de baixo pra cima.
 */
const now = ref(Date.now());
let clock = 0;
const packProgress = computed(() => {
    const status = page.props.packStatus;

    if (!status?.nextPackAt) {
        return 1;
    }

    const remaining = new Date(status.nextPackAt).getTime() - now.value;
    const cooldown = status.cooldownHours * 3600 * 1000;

    return Math.min(1, Math.max(0, 1 - remaining / cooldown));
});
const isPackReady = computed(() => packProgress.value >= 1);

const pendingFriendRequests = computed(
    () => page.props.pendingFriendRequests ?? 0,
);

const hovered = ref<string | null>(null);
const isMenuOpen = ref(false);

function onEnter(key: string, event: PointerEvent) {
    // O rótulo é dica de mouse: no toque ele ficaria preso, porque o dock
    // não é recriado entre as telas.
    if (event.pointerType === 'mouse') {
        hovered.value = key;
    }

    const target = event.currentTarget as HTMLElement;
    animate(
        target,
        [
            { transform: 'none' },
            { transform: 'translateY(-6px) scale(1.08)', offset: 0.4 },
            { transform: 'none' },
        ],
        520,
        { easing: 'ease-out', fill: 'none' },
    );
    target.querySelectorAll('[data-a]').forEach((part, k) => {
        const frames = MICRO[part.getAttribute('data-a') ?? ''];

        if (frames) {
            animate(
                part,
                frames.map((transform) => ({ transform })),
                560,
                {
                    delay: k * 50,
                    easing: 'ease-in-out',
                    fill: 'none',
                },
            );
        }
    });
}

const root = ref<HTMLElement | null>(null);

function closeMenuOnOutside(event: PointerEvent) {
    if (isMenuOpen.value && !root.value?.contains(event.target as Node)) {
        isMenuOpen.value = false;
    }
}

function closeMenuOnEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        isMenuOpen.value = false;
    }
}

onMounted(() => {
    clock = window.setInterval(() => (now.value = Date.now()), 30_000);
    document.addEventListener('pointerdown', closeMenuOnOutside);
    document.addEventListener('keydown', closeMenuOnEscape);
});

onBeforeUnmount(() => {
    clearInterval(clock);
    document.removeEventListener('pointerdown', closeMenuOnOutside);
    document.removeEventListener('keydown', closeMenuOnEscape);
});

/** Foco por teclado mostra o rótulo; o foco que sobra de um toque, não. */
function onFocus(key: string, event: FocusEvent) {
    if ((event.target as HTMLElement).matches(':focus-visible')) {
        hovered.value = key;
    }
}

function onActivate(key: string) {
    hovered.value = null;

    if (key === 'settings') {
        isMenuOpen.value = !isMenuOpen.value;
    }
}
</script>

<template>
    <nav
        ref="root"
        aria-label="Navegação principal"
        class="relative flex items-center gap-1.5 rounded-full bg-white/70 p-[7px] shadow-[0_0_0_1px_rgba(28,32,38,0.05),0_10px_26px_rgba(28,32,38,0.08)] backdrop-blur-[12px] max-[400px]:gap-[3px] max-[400px]:p-[5px] sm:gap-2.5 sm:p-[9px]"
    >
        <Link
            :href="home()"
            :prefetch="['hover', 'click']"
            cache-for="10s"
            aria-label="Início"
            :aria-current="active === 'home' ? 'page' : undefined"
            class="relative flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white max-[400px]:size-[38px] sm:size-14"
            :class="
                active === 'home'
                    ? 'shadow-[0_0_0_2.5px_#f24d4d,0_6px_14px_rgba(242,77,77,0.22)]'
                    : 'shadow-[0_1px_2px_rgba(28,32,38,0.06)]'
            "
            @pointerenter="onEnter('home', $event)"
            @pointerleave="hovered = null"
            @focus="onFocus('home', $event)"
            @blur="hovered = null"
            @click="hovered = null"
        >
            <svg
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2b2f36"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path data-a="roof" d="M3.5 11 12 4l8.5 7" />
                <path
                    d="M6 9.5V19a1 1 0 0 0 1 1h3.5v-5h3v5H17a1 1 0 0 0 1-1V9.5"
                />
            </svg>
            <span
                v-if="hovered === 'home'"
                class="bg-ink pointer-events-none absolute bottom-[68px] left-1/2 -translate-x-1/2 rounded-full px-3 py-1.5 text-[12.5px] font-extrabold whitespace-nowrap text-white"
            >
                Início
            </span>
        </Link>

        <Link
            :href="packShow()"
            :aria-label="
                isPackReady ? 'Abrir o pack do dia' : 'Pack do dia carregando'
            "
            class="relative flex size-11 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-full bg-[#fbd5d5] transition-transform active:scale-95 max-[400px]:size-[38px] sm:size-14"
            :class="
                isPackReady
                    ? 'shadow-[0_6px_14px_rgba(242,77,77,0.3)]'
                    : 'shadow-[0_1px_2px_rgba(28,32,38,0.06)]'
            "
        >
            <span
                class="bg-brand absolute inset-x-0 bottom-0 transition-[height] duration-700"
                :style="{ height: `${packProgress * 100}%` }"
                aria-hidden="true"
            />
            <svg
                width="34"
                height="34"
                viewBox="51 43 708 708"
                class="relative block size-7 max-[400px]:size-6 sm:size-[34px]"
                aria-hidden="true"
            >
                <circle cx="405" cy="414" r="353" fill="#ffffff" />
                <path
                    d="M 51.9 410 C 54 260 150 120 287.3 65.6 C 323.5 52.2 364.8 43.4 405 43.4 C 445 43.4 484 50.8 520 64 C 655 117 755 255 758 410 Z"
                    fill="#ff5757"
                />
                <rect
                    x="51.7"
                    y="352"
                    width="707"
                    height="106"
                    rx="8"
                    fill="#434a53"
                />
                <circle cx="405" cy="405" r="119" fill="#434a53" />
                <circle cx="405" cy="405" r="62.7" fill="#e4e7eb" />
            </svg>
        </Link>

        <component
            :is="item.href ? Link : 'button'"
            v-for="item in items"
            :key="item.key"
            :href="item.href?.()"
            :prefetch="item.href ? ['hover', 'click'] : undefined"
            :cache-for="item.href ? '10s' : undefined"
            :type="item.href ? undefined : 'button'"
            :aria-label="item.label"
            :aria-current="active === item.key ? 'page' : undefined"
            :aria-haspopup="item.key === 'settings' ? 'menu' : undefined"
            :aria-expanded="item.key === 'settings' ? isMenuOpen : undefined"
            class="relative flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white max-[400px]:size-[38px] sm:size-14"
            :class="
                active === item.key
                    ? 'shadow-[0_0_0_2.5px_#f24d4d,0_6px_14px_rgba(242,77,77,0.22)]'
                    : 'shadow-[0_1px_2px_rgba(28,32,38,0.06)]'
            "
            @pointerenter="onEnter(item.key, $event)"
            @pointerleave="hovered = null"
            @focus="onFocus(item.key, $event)"
            @blur="hovered = null"
            @click="onActivate(item.key)"
        >
            <svg
                v-if="item.key === 'collection'"
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2b2f36"
                stroke-width="2.2"
                stroke-linejoin="round"
            >
                <rect
                    data-a="cardBack"
                    x="3.5"
                    y="5"
                    width="10.5"
                    height="14"
                    rx="2.5"
                    style="transform-box: fill-box; transform-origin: 0% 100%"
                />
                <rect
                    data-a="cardFront"
                    x="10"
                    y="5"
                    width="10.5"
                    height="14"
                    rx="2.5"
                    fill="#ffffff"
                    style="transform-box: fill-box; transform-origin: 100% 100%"
                />
            </svg>
            <svg
                v-else-if="item.key === 'packs'"
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2b2f36"
                stroke-width="2.2"
                stroke-linejoin="round"
            >
                <path
                    data-a="capTop"
                    d="M4 10.5a8 6.5 0 0 1 16 0z"
                    style="transform-box: fill-box; transform-origin: 0% 100%"
                />
                <path
                    data-a="capBot"
                    d="M4 13.5a8 6.5 0 0 0 16 0z"
                    style="transform-box: fill-box; transform-origin: 50% 0%"
                />
            </svg>
            <svg
                v-else-if="item.key === 'ranking'"
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2b2f36"
                stroke-width="2.6"
                stroke-linecap="round"
            >
                <path
                    data-a="b1"
                    d="M6 19v-7"
                    style="transform-box: fill-box; transform-origin: 50% 100%"
                />
                <path
                    data-a="b2"
                    d="M12 19V6"
                    style="transform-box: fill-box; transform-origin: 50% 100%"
                />
                <path
                    data-a="b3"
                    d="M18 19v-9"
                    style="transform-box: fill-box; transform-origin: 50% 100%"
                />
            </svg>
            <svg
                v-else-if="item.key === 'trades'"
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2b2f36"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <g data-a="arR">
                    <path d="M5 9h13" />
                    <path d="M15 6l3 3-3 3" />
                </g>
                <g data-a="arL">
                    <path d="M19 15H6" />
                    <path d="M9 12l-3 3 3 3" />
                </g>
            </svg>
            <svg
                v-else-if="item.key === 'friends'"
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2b2f36"
                stroke-width="2.2"
                stroke-linecap="round"
            >
                <circle cx="9" cy="9" r="3.2" />
                <path d="M3.5 19a5.5 5 0 0 1 11 0" />
                <g
                    data-a="wave"
                    style="transform-box: fill-box; transform-origin: 50% 100%"
                >
                    <circle cx="16.5" cy="8" r="2.6" />
                    <path d="M14.8 13.2a5 4.6 0 0 1 6.7 4.8" />
                </g>
            </svg>
            <svg
                v-else
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#2b2f36"
                stroke-width="2.2"
                stroke-linecap="round"
            >
                <path d="M4 8h16M4 16h16" />
                <circle data-a="k1" cx="9" cy="8" r="2.5" fill="#ffffff" />
                <circle data-a="k2" cx="15" cy="16" r="2.5" fill="#ffffff" />
            </svg>

            <span
                v-if="item.key === 'friends' && pendingFriendRequests > 0"
                class="bg-brand absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-1 text-[11px] leading-none font-black text-white shadow-[0_0_0_2px_#fff]"
                :aria-label="`${pendingFriendRequests} pedidos de amizade`"
                >{{ pendingFriendRequests }}</span
            >
            <span
                v-if="
                    hovered === item.key &&
                    !(item.key === 'settings' && isMenuOpen)
                "
                class="bg-ink pointer-events-none absolute bottom-[68px] left-1/2 -translate-x-1/2 rounded-full px-3 py-1.5 text-[12.5px] font-extrabold whitespace-nowrap text-white"
            >
                {{ item.label }}
            </span>
        </component>

        <div
            v-if="isMenuOpen"
            role="menu"
            class="absolute right-0 bottom-[calc(100%+12px)] flex min-w-44 flex-col gap-1 rounded-[22px] bg-white p-2 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.12)]"
        >
            <Link
                :href="profileEdit()"
                role="menuitem"
                class="text-ink hover:bg-field rounded-full px-4 py-2.5 text-sm font-extrabold"
            >
                Editar perfil
            </Link>
            <Link
                v-if="isAdmin"
                :href="adminIndex()"
                role="menuitem"
                class="text-ink hover:bg-field rounded-full px-4 py-2.5 text-sm font-extrabold"
            >
                Painel de admin
            </Link>
            <Link
                :href="logout()"
                as="button"
                role="menuitem"
                class="text-brand-ink hover:bg-field cursor-pointer rounded-full px-4 py-2.5 text-left text-sm font-extrabold"
            >
                Sair
            </Link>
        </div>
    </nav>
</template>
