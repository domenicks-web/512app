<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { animate } from '@/lib/motion';
import { logout } from '@/routes';
import { index as adminIndex } from '@/routes/admin';

/**
 * Dock de vidro da home. Cada ícone tem uma micro-animação no hover
 * (cartas em leque, cápsula abre, barras sobem, setas trocam, mão acena,
 * sliders correm). Só "Ajustes" faz algo hoje (menu com sair/admin); o
 * resto aguarda as telas de verdade — ver pendências no CLAUDE.md.
 */
defineProps<{
    isAdmin: boolean;
}>();

type DockItem = { key: string; label: string };

const items: DockItem[] = [
    { key: 'collection', label: 'Coleção' },
    { key: 'packs', label: 'Packs' },
    { key: 'ranking', label: 'Ranking' },
    { key: 'trades', label: 'Trocas' },
    { key: 'friends', label: 'Amigos' },
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
};

const hovered = ref<string | null>(null);
const isMenuOpen = ref(false);

function onEnter(key: string, event: PointerEvent) {
    hovered.value = key;
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
    document.addEventListener('pointerdown', closeMenuOnOutside);
    document.addEventListener('keydown', closeMenuOnEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeMenuOnOutside);
    document.removeEventListener('keydown', closeMenuOnEscape);
});

function onActivate(key: string) {
    if (key === 'settings') {
        isMenuOpen.value = !isMenuOpen.value;
    }
}
</script>

<template>
    <nav
        ref="root"
        aria-label="Navegação principal"
        class="relative flex items-center gap-1.5 rounded-full bg-white/70 p-[7px] shadow-[0_0_0_1px_rgba(28,32,38,0.05),0_10px_26px_rgba(28,32,38,0.08)] backdrop-blur-[12px] sm:gap-2.5 sm:p-[9px]"
    >
        <div
            class="bg-brand flex size-11 shrink-0 items-center justify-center rounded-full shadow-[0_6px_14px_rgba(242,77,77,0.3)] sm:size-14"
            aria-hidden="true"
        >
            <svg
                width="34"
                height="34"
                viewBox="51 43 708 708"
                class="block size-7 sm:size-[34px]"
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
        </div>

        <button
            v-for="item in items"
            :key="item.key"
            type="button"
            :aria-label="item.label"
            :aria-haspopup="item.key === 'settings' ? 'menu' : undefined"
            :aria-expanded="item.key === 'settings' ? isMenuOpen : undefined"
            class="relative flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white shadow-[0_1px_2px_rgba(28,32,38,0.06)] sm:size-14"
            @pointerenter="onEnter(item.key, $event)"
            @pointerleave="hovered = null"
            @focus="hovered = item.key"
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
                v-if="
                    hovered === item.key &&
                    !(item.key === 'settings' && isMenuOpen)
                "
                class="bg-ink pointer-events-none absolute bottom-[68px] left-1/2 -translate-x-1/2 rounded-full px-3 py-1.5 text-[12.5px] font-extrabold whitespace-nowrap text-white"
            >
                {{ item.label }}
            </span>
        </button>

        <div
            v-if="isMenuOpen"
            role="menu"
            class="absolute right-0 bottom-[calc(100%+12px)] flex min-w-44 flex-col gap-1 rounded-[22px] bg-white p-2 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.12)]"
        >
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
