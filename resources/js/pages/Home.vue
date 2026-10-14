<script setup lang="ts">
import FittedSprite from '@/components/FittedSprite.vue';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    computed,
    inject,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { groupNavigationKey } from '@/layouts/groupNavigation';
import { formatCountdown, formatFriendsOnline, formatMint } from '@/lib/format';
import { animate, spring } from '@/lib/motion';
import { rarityLook } from '@/lib/pack/rarity';
import type { Tier } from '@/lib/pack/types';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import { edit as profileEdit } from '@/routes/profile';
import { show as collectionShow } from '@/routes/collection';
import { show as feedShow } from '@/routes/feed';
import { show as packShow } from '@/routes/pack';
import { show as rankingShow } from '@/routes/ranking';
import { index as tradesIndex } from '@/routes/trades';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Home: trilho de tiles com um anel colorido que desliza até o ativo
 * (setas, hover ou clique; Enter "aperta"). Apertar Coleção, Ranking,
 * Trocas ou Feed abre a tela; o Pack de hoje levanta a tampa e vai pra
 * abertura. O tile do pack mostra "Disponível" ou a contagem até o próximo.
 */
const props = defineProps<{
    trainer: {
        nickname: string;
        tag: string;
        avatarSpritePath: string | null;
        isAdmin: boolean;
    };
    collection: {
        ownedSpecies: number;
        totalSpecies: number;
        shinyCount: number;
    };
    bestSpecimen: {
        speciesName: string;
        spritePath: string;
        isShiny: boolean;
        rarityTier: string;
        mintNumber: number;
    } | null;
    silhouetteSpritePath: string | null;
    rankingTop: {
        nickname: string;
        avatarSpritePath: string | null;
        ownedSpecies: number;
        position: number | null;
        isViewer: boolean;
    }[];
    sentTrades: number;
    duplicates: { count: number; spritePaths: string[] };
    nextPackAt: string | null;
    rankingPosition: number | null;
    pendingTrades: number;
    feedHighlight: {
        isShiny: boolean;
        trainerNickname: string;
        speciesName: string;
        mintNumber: number;
        caughtAgo: string;
    } | null;
    friendsOnline: number;
    presencePollSeconds: number;
}>();

usePoll(props.presencePollSeconds * 1000, {
    only: ['friendsOnline', 'pendingFriendRequests'],
});

/** Tile → tela que ele abre depois de apertado. */
const TILE_DESTINATIONS: Partial<Record<number, () => { url: string }>> = {
    0: packShow,
    1: collectionShow,
    2: rankingShow,
    3: tradesIndex,
    4: feedShow,
};
const PRESS_BEFORE_VISIT = 300;

const TILE_LABELS = [
    'Pack de hoje',
    'Coleção',
    'Ranking',
    'Trocas',
    'Feed do grupo',
];

const groupNavigation = inject(groupNavigationKey, null);
const field = useDotField();
const root = ref<HTMLElement | null>(null);
const wrap = ref<HTMLElement | null>(null);
const track = ref<HTMLElement | null>(null);
const ring = ref<HTMLElement | null>(null);
const ringLabel = ref<HTMLElement | null>(null);
const activeTile = ref(0);

/**
 * Abaixo de 640px o trilho vira mosaico (pack largo em cima, os outros em
 * 2×2) pra caber tudo na tela; aí não tem deslize nem rótulo flutuante.
 */
const isCompact = ref(false);
let compactQuery: MediaQueryList | null = null;

const now = ref(Date.now());
let clock = 0;

const isPackAvailable = computed(
    () =>
        props.nextPackAt === null ||
        new Date(props.nextPackAt).getTime() <= now.value,
);

const packStatus = computed(() =>
    isPackAvailable.value || props.nextPackAt === null
        ? 'Disponível'
        : `Próximo em ${formatCountdown(props.nextPackAt, now.value)}`,
);

const bestLook = computed(() =>
    props.bestSpecimen
        ? rarityLook(
              props.bestSpecimen.rarityTier as Tier,
              props.bestSpecimen.isShiny,
          )
        : null,
);

const collectionPercent = computed(() =>
    props.collection.totalSpecies > 0
        ? Math.round(
              (props.collection.ownedSpecies / props.collection.totalSpecies) *
                  100,
          )
        : 0,
);

function tiles(): HTMLElement[] {
    return track.value
        ? [...track.value.querySelectorAll<HTMLElement>('[data-tile]')]
        : [];
}

/** Move o anel e o rótulo até o tile ativo e rola o trilho pra mantê-lo visível. */
function placeRing(animated: boolean) {
    const tile = tiles()[activeTile.value];

    if (
        !track.value ||
        !ring.value ||
        !ringLabel.value ||
        !wrap.value ||
        !tile
    ) {
        return;
    }

    const paddingLeft =
        parseFloat(getComputedStyle(wrap.value).paddingLeft) || 0;
    const visible = wrap.value.clientWidth - paddingLeft;
    const shift = isCompact.value
        ? 0
        : Math.max(
              0,
              Math.min(
                  tile.offsetLeft - 8,
                  tile.offsetLeft + tile.offsetWidth - visible + 70,
              ),
          );
    const elements = [track.value, ring.value, ringLabel.value];
    const transitions = elements.map((element) => element.style.transition);

    if (!animated) {
        elements.forEach((element) => (element.style.transition = 'none'));
    }

    track.value.style.transform = `translateX(${-shift}px)`;
    Object.assign(ring.value.style, {
        left: `${tile.offsetLeft - 8}px`,
        top: `${tile.offsetTop - 8}px`,
        width: `${tile.offsetWidth + 16}px`,
        height: `${tile.offsetHeight + 16}px`,
    });
    ringLabel.value.style.left = `${tile.offsetLeft}px`;
    ringLabel.value.style.top = `${tile.offsetTop - 34}px`;

    if (!animated) {
        void track.value.offsetWidth;
        elements.forEach(
            (element, k) => (element.style.transition = transitions[k]),
        );
    }
}

watch(activeTile, async () => {
    await nextTick();
    placeRing(true);
    animate(
        ringLabel.value,
        [
            { opacity: 0, transform: 'translateY(8px)' },
            { opacity: 1, transform: 'none' },
        ],
        380,
        {
            easing: spring(220, 18),
        },
    );
});

function pressTile(index: number) {
    const tile = tiles()[index];
    animate(
        tile,
        [
            { transform: 'none' },
            { transform: 'scale(.94)', offset: 0.25 },
            { transform: 'scale(1.03)', offset: 0.6 },
            { transform: 'none' },
        ],
        460,
        { fill: 'none', easing: 'ease-out' },
    );

    // Pack de hoje: a tampa levanta antes de ir pra abertura.
    if (index === 0) {
        animate(
            tile?.querySelector('[data-k="ptop"]'),
            [
                { transform: 'none' },
                { transform: 'translateY(-14px) rotate(-18deg)', offset: 0.35 },
                { transform: 'translateY(-14px) rotate(-18deg)', offset: 0.6 },
                { transform: 'none' },
            ],
            900,
            { fill: 'none', easing: 'ease-in-out' },
        );
    }

    const destination = TILE_DESTINATIONS[index];

    if (destination) {
        window.setTimeout(
            () => router.visit(destination().url),
            PRESS_BEFORE_VISIT,
        );
    }
}

function selectTile(index: number) {
    if (activeTile.value === index) {
        pressTile(index);
    } else {
        activeTile.value = index;
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') {
        activeTile.value = Math.min(
            TILE_LABELS.length - 1,
            activeTile.value + 1,
        );
    } else if (event.key === 'ArrowLeft') {
        activeTile.value = Math.max(0, activeTile.value - 1);
    } else if (
        event.key === 'Enter' &&
        (event.target as HTMLElement | null)?.closest('[data-tile]')
    ) {
        event.preventDefault();
        pressTile(activeTile.value);
    }
}

const onResize = () => placeRing(false);

async function onCompactChange() {
    isCompact.value = compactQuery?.matches ?? false;
    await nextTick();
    placeRing(false);
}

onMounted(async () => {
    compactQuery = window.matchMedia('(max-width: 639.98px)');
    isCompact.value = compactQuery.matches;
    compactQuery.addEventListener('change', onCompactChange);
    await waitForIntro();
    field.hide();
    await nextTick();
    placeRing(false);

    // Chegou deslizando de outra aba: sem a entrada em cascata.
    if (!groupNavigation?.isSlideNavigation()) {
        playEntrance();
    }

    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);
});

function playEntrance() {
    const keyframes: Record<string, Keyframe[]> = {
        pop: [
            { transform: 'scale(.3)', opacity: 0 },
            { transform: 'none', opacity: 1 },
        ],
        rise: [
            { transform: 'translateY(50px)', opacity: 0 },
            { transform: 'none', opacity: 1 },
        ],
        slide: [
            {
                transform: 'translateX(180px) rotate(5deg) scale(.9)',
                opacity: 0,
            },
            { transform: 'none', opacity: 1 },
        ],
    };
    const base: Record<string, number> = { pop: 0, slide: 220, rise: 560 };

    root.value
        ?.querySelectorAll<HTMLElement>('[data-enter]')
        .forEach((element) => {
            const kind = element.dataset.enter ?? 'rise';
            animate(element, keyframes[kind] ?? keyframes.rise, 900, {
                delay: (base[kind] ?? 0) + Number(element.dataset.delay ?? 0),
                easing: spring(170, 16),
            });
        });
    animate(
        ring.value,
        [
            { opacity: 0, transform: 'scale(1.18)' },
            { opacity: 1, transform: 'none' },
        ],
        700,
        {
            delay: 720,
            easing: spring(240, 15),
        },
    );
    animate(
        ringLabel.value,
        [
            { opacity: 0, transform: 'translateY(10px)' },
            { opacity: 1, transform: 'none' },
        ],
        500,
        {
            delay: 780,
            easing: spring(220, 18),
        },
    );
    animate(
        root.value?.querySelector('[data-k="ptoken"]'),
        [
            { transform: 'translateY(0) rotate(0)' },
            { transform: 'translateY(-7px) rotate(-3deg)' },
            { transform: 'translateY(0) rotate(0)' },
        ],
        3200,
        {
            easing: 'ease-in-out',
            iterations: Infinity,
            fill: 'none',
            delay: 1200,
        },
    );
}

onMounted(() => {
    clock = window.setInterval(() => (now.value = Date.now()), 1000);
});

onBeforeUnmount(() => {
    clearInterval(clock);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
    compactQuery?.removeEventListener('change', onCompactChange);
});

const tileBase =
    'relative min-h-0 w-full cursor-pointer overflow-hidden rounded-[22px] text-left outline-none sm:aspect-square sm:w-[clamp(200px,23vw,300px)] sm:shrink-0';

/** Título dentro do tile, só no mosaico do celular. */
const compactLabel =
    'absolute top-3.5 left-4 text-[12px] font-black tracking-[0.02em] sm:hidden';
</script>

<template>
    <Head title="Início" />

    <div ref="root" class="absolute inset-0 flex flex-col">
        <header
            class="flex items-center justify-between gap-4 px-5 pt-[18px] sm:px-7 sm:pt-[22px]"
        >
            <Link
                :href="profileEdit.url()"
                data-enter="pop"
                class="flex items-center gap-3 rounded-full pr-3 outline-none focus-visible:shadow-[0_0_0_3px_rgba(242,77,77,0.35)]"
                aria-label="Editar perfil"
            >
                <div
                    class="flex size-12 items-center justify-center overflow-hidden rounded-full bg-white shadow-[0_0_0_3px_#fff,0_0_0_5px_#f24d4d,0_6px_14px_rgba(28,32,38,0.12)]"
                >
                    <FittedSprite
                        v-if="trainer.avatarSpritePath"
                        :src="`/storage/${trainer.avatarSpritePath}`"
                        :size="48"
                        :padding="7"
                    />
                </div>
                <div class="flex flex-col gap-px">
                    <div class="text-ink text-[15.5px] font-black">
                        {{ trainer.nickname
                        }}<span class="text-faint-2">#{{ trainer.tag }}</span>
                    </div>
                    <div class="text-soft text-xs font-semibold">
                        {{ collection.ownedSpecies }} de
                        {{ collection.totalSpecies }} espécies
                    </div>
                </div>
            </Link>
            <div
                data-enter="rise"
                data-delay="120"
                class="flex items-center gap-[9px] sm:pl-[150px]"
            >
                <div class="relative size-[9px]" aria-hidden="true">
                    <div
                        v-if="friendsOnline > 0"
                        class="bg-online animate-online-pulse absolute inset-0 rounded-full"
                    />
                    <div
                        class="absolute inset-0 rounded-full"
                        :class="friendsOnline > 0 ? 'bg-online' : 'bg-faint-2'"
                    />
                </div>
                <div class="text-muted text-[12.5px] font-extrabold">
                    {{ formatFriendsOnline(friendsOnline) }}
                </div>
            </div>
        </header>

        <div
            ref="wrap"
            class="flex min-h-0 flex-1 flex-col justify-center overflow-hidden px-4 py-4 sm:py-0 sm:pr-0 sm:pl-[clamp(24px,7vw,96px)]"
        >
            <div
                ref="track"
                role="listbox"
                aria-label="Atalhos"
                :aria-activedescendant="`home-tile-${activeTile}`"
                class="relative grid h-full max-h-[640px] grid-cols-2 grid-rows-[1.3fr_1fr_1fr] gap-3 transition-transform duration-700 ease-[cubic-bezier(.34,1.3,.64,1)] sm:flex sm:h-auto sm:max-h-none sm:gap-[22px] sm:pt-10"
            >
                <div
                    ref="ringLabel"
                    class="absolute top-0 left-0 hidden items-center gap-2 whitespace-nowrap transition-[left] duration-[550ms] ease-[cubic-bezier(.34,1.4,.64,1)] sm:flex"
                    aria-hidden="true"
                >
                    <div class="bg-brand size-3.5 rounded" />
                    <div class="text-ink text-[15px] font-black">
                        {{ TILE_LABELS[activeTile] }}
                    </div>
                </div>
                <div
                    ref="ring"
                    class="pointer-events-none absolute top-0 left-0 z-[2] rounded-[30px] transition-[left,top,width,height] duration-[550ms] ease-[cubic-bezier(.34,1.4,.64,1)]"
                    aria-hidden="true"
                >
                    <div
                        class="ring-mask absolute inset-0 overflow-hidden rounded-[30px] p-1"
                    >
                        <div
                            class="animate-spin-ring-slow absolute -inset-1/2 bg-[conic-gradient(#f24d4d,#ffffff,#f24d4d)]"
                        />
                    </div>
                </div>

                <button
                    id="home-tile-0"
                    type="button"
                    role="option"
                    :aria-selected="activeTile === 0"
                    data-tile
                    data-enter="slide"
                    data-delay="0"
                    :class="tileBase"
                    class="bg-brand col-span-2 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(242,77,77,0.25)]"
                    @pointerenter="activeTile = 0"
                    @focus="activeTile = 0"
                    @click="selectTile(0)"
                >
                    <span :class="compactLabel" class="text-white"
                        >Pack de hoje</span
                    >
                    <span
                        class="absolute inset-0 flex items-center justify-center"
                    >
                        <span
                            data-k="ptoken"
                            class="relative block size-[104px] shrink-0"
                        >
                            <span
                                data-k="ptop"
                                class="bg-brand-cap absolute top-0 left-0 block h-[52px] w-[104px] origin-[6%_100%] rounded-[52px_52px_0_0]"
                            />
                            <span
                                class="absolute top-[52px] left-0 block h-[52px] w-[104px] origin-[6%_0%] rounded-[0_0_52px_52px] bg-white"
                            >
                                <span
                                    class="bg-steel absolute -top-2 right-0 left-0 block h-4 rounded-[3px]"
                                />
                                <span
                                    class="bg-steel absolute top-0 left-1/2 -mt-[17.5px] -ml-[17.5px] flex size-[35px] items-center justify-center rounded-full"
                                >
                                    <span
                                        class="bg-steel-light block size-[19px] rounded-full"
                                    />
                                </span>
                            </span>
                        </span>
                    </span>
                    <span
                        class="absolute bottom-3.5 left-4 rounded-full px-3 py-1.5 text-[12.5px] font-black tabular-nums sm:bottom-4"
                        :class="
                            isPackAvailable
                                ? 'text-brand-ink bg-white'
                                : 'bg-white/20 text-white'
                        "
                    >
                        {{ packStatus }}
                    </span>
                </button>

                <button
                    id="home-tile-1"
                    type="button"
                    role="option"
                    :aria-selected="activeTile === 1"
                    data-tile
                    data-enter="slide"
                    data-delay="70"
                    :class="tileBase"
                    class="flex flex-col justify-end gap-2.5 bg-white p-4 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)] sm:gap-3 sm:p-5"
                    @pointerenter="activeTile = 1"
                    @focus="activeTile = 1"
                    @click="selectTile(1)"
                >
                    <span :class="compactLabel" class="text-soft z-[1]"
                        >Coleção</span
                    >
                    <!-- Sprite grande de fundo, inclinado e cortado pela borda do
                         card (img solta de propósito: aqui ele não é encaixado,
                         é decoração). O degradê embaixo protege os números. -->
                    <span
                        class="pointer-events-none absolute inset-0"
                        aria-hidden="true"
                        :style="
                            bestLook
                                ? {
                                      background: `radial-gradient(70% 60% at 85% 18%, ${bestLook.badge}33, transparent 70%)`,
                                  }
                                : undefined
                        "
                    >
                        <img
                            v-if="bestSpecimen || silhouetteSpritePath"
                            :src="`/storage/${bestSpecimen?.spritePath ?? silhouetteSpritePath}`"
                            alt=""
                            class="absolute -top-[10%] -right-[34%] h-[104%] max-w-none rotate-[-12deg] [image-rendering:pixelated]"
                            :class="
                                bestSpecimen
                                    ? 'drop-shadow-[0_10px_18px_rgba(28,32,38,0.18)]'
                                    : 'opacity-[0.07] brightness-0'
                            "
                        />
                        <span
                            class="absolute inset-y-0 left-0 w-[62%] bg-gradient-to-r from-white via-white/75 to-white/0"
                        />
                        <span
                            class="absolute inset-x-0 bottom-0 h-[42%] bg-gradient-to-t from-white via-white/80 to-white/0"
                        />
                    </span>
                    <span
                        class="relative hidden items-center gap-1.5 text-[13px] font-black sm:flex"
                    >
                        <template v-if="bestSpecimen && bestLook">
                            <span class="text-ink">{{
                                bestSpecimen.speciesName
                            }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] tracking-[0.06em] uppercase"
                                :style="{
                                    background: bestLook.badge,
                                    color: bestLook.badgeInk,
                                }"
                                >{{ bestLook.label }}</span
                            >
                        </template>
                        <span v-else class="text-faint-2"
                            >??? · abra um pack</span
                        >
                    </span>
                    <span class="relative flex items-baseline gap-1">
                        <span
                            class="text-ink text-[40px] leading-[0.9] font-black tracking-[-0.03em] sm:text-[64px]"
                            >{{ collection.ownedSpecies }}</span
                        >
                        <span
                            class="text-faint-2 text-[16px] font-black sm:text-[22px]"
                            >/{{ collection.totalSpecies }}</span
                        >
                        <span
                            v-if="collection.shinyCount > 0"
                            class="text-shiny ml-auto hidden text-xs font-black sm:inline"
                            >✦ {{ collection.shinyCount }}
                            {{
                                collection.shinyCount === 1
                                    ? 'shiny'
                                    : 'shinies'
                            }}</span
                        >
                    </span>
                    <span
                        class="bg-base relative block h-2 overflow-hidden rounded-full"
                    >
                        <span
                            class="bg-brand block h-full rounded-full"
                            :style="{ width: `${collectionPercent}%` }"
                        />
                    </span>
                </button>

                <button
                    id="home-tile-2"
                    type="button"
                    role="option"
                    :aria-selected="activeTile === 2"
                    data-tile
                    data-enter="slide"
                    data-delay="140"
                    :class="tileBase"
                    class="bg-ink flex flex-col justify-end gap-1 p-4 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)] sm:p-5"
                    @pointerenter="activeTile = 2"
                    @focus="activeTile = 2"
                    @click="selectTile(2)"
                >
                    <span :class="compactLabel" class="text-[#c3cad4]"
                        >Ranking</span
                    >
                    <span
                        class="absolute inset-x-5 top-5 hidden flex-col gap-2 sm:flex"
                    >
                        <span
                            v-for="(row, index) in rankingTop"
                            :key="index"
                            class="flex items-center gap-2.5 rounded-full py-1 pr-3 pl-1"
                            :class="row.isViewer ? 'bg-white/12' : ''"
                        >
                            <span
                                class="w-5 text-center text-[13px] font-black text-[#7d8794]"
                                >{{
                                    row.position === null
                                        ? '—'
                                        : `${row.position}º`
                                }}</span
                            >
                            <TrainerAvatar
                                :sprite-path="row.avatarSpritePath"
                                size="sm"
                                class="!size-8"
                            />
                            <span
                                class="min-w-0 flex-1 truncate text-[13.5px] font-black text-white"
                                >{{ row.nickname }}</span
                            >
                            <span
                                class="text-[13px] font-black text-[#c3cad4]"
                                >{{ row.ownedSpecies }}</span
                            >
                        </span>
                    </span>
                    <span
                        class="text-[44px] leading-[0.9] font-black tracking-[-0.03em] text-white sm:text-[72px]"
                        >{{
                            rankingPosition === null
                                ? '—'
                                : `${rankingPosition}º`
                        }}</span
                    >
                    <span
                        class="text-xs leading-tight font-extrabold text-[#c3cad4] sm:text-sm"
                        >{{
                            rankingPosition === null
                                ? 'abra um pack pra entrar'
                                : 'em espécies no grupo'
                        }}</span
                    >
                </button>

                <button
                    id="home-tile-3"
                    type="button"
                    role="option"
                    :aria-selected="activeTile === 3"
                    data-tile
                    data-enter="slide"
                    data-delay="210"
                    :class="tileBase"
                    class="flex flex-col justify-end gap-1 bg-white p-4 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)] sm:p-5"
                    @pointerenter="activeTile = 3"
                    @focus="activeTile = 3"
                    @click="selectTile(3)"
                >
                    <span :class="compactLabel" class="text-soft">Trocas</span>
                    <span
                        v-if="duplicates.spritePaths.length > 0"
                        class="absolute inset-x-5 top-5 hidden flex-col gap-2 sm:flex"
                    >
                        <span class="text-soft text-[12px] font-black"
                            >{{ duplicates.count }}
                            {{
                                duplicates.count === 1
                                    ? 'repetido pra trocar'
                                    : 'repetidos pra trocar'
                            }}</span
                        >
                        <span class="flex">
                            <span
                                v-for="(path, index) in duplicates.spritePaths"
                                :key="path"
                                class="-ml-3 flex size-[62px] items-center justify-center rounded-[16px] bg-[#f4f5f6] shadow-[0_0_0_3px_#fff] first:ml-0"
                                :style="{
                                    transform: `rotate(${(index % 2 ? 1 : -1) * (4 + index)}deg)`,
                                }"
                            >
                                <FittedSprite
                                    :src="`/storage/${path}`"
                                    :size="62"
                                    :padding="6"
                                />
                            </span>
                        </span>
                    </span>
                    <span
                        class="text-ink text-[44px] leading-[0.9] font-black tracking-[-0.03em] sm:text-[72px]"
                        >{{ pendingTrades }}</span
                    >
                    <span
                        class="text-soft text-xs leading-tight font-extrabold sm:text-sm"
                        >{{
                            pendingTrades === 1
                                ? 'proposta recebida'
                                : 'propostas recebidas'
                        }}<span class="hidden sm:inline">
                            · {{ sentTrades }}
                            {{
                                sentTrades === 1 ? 'enviada' : 'enviadas'
                            }}</span
                        ></span
                    >
                </button>

                <button
                    id="home-tile-4"
                    type="button"
                    role="option"
                    :aria-selected="activeTile === 4"
                    data-tile
                    data-enter="slide"
                    data-delay="280"
                    :class="tileBase"
                    class="bg-screen-dark flex flex-col justify-end gap-1 p-4 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.16)] sm:gap-1.5 sm:p-5"
                    @pointerenter="activeTile = 4"
                    @focus="activeTile = 4"
                    @click="selectTile(4)"
                >
                    <span :class="compactLabel" class="z-[1] text-[#7d8794]"
                        >Feed</span
                    >
                    <span
                        class="absolute inset-0 bg-[radial-gradient(70%_45%_at_50%_46%,rgba(77,217,232,0.16),rgba(77,217,232,0))]"
                    />
                    <span
                        v-if="feedHighlight?.isShiny"
                        class="text-shiny relative flex h-[22px] items-center self-start rounded-full bg-[rgba(77,217,232,0.14)] px-2.5 text-xs font-extrabold tracking-[0.06em] uppercase sm:h-[26px]"
                    >
                        Shiny
                    </span>
                    <span
                        class="relative line-clamp-3 text-[15px] leading-[1.1] font-black tracking-[-0.02em] text-balance text-white sm:line-clamp-none sm:text-[30px] sm:leading-[1.05] sm:tracking-[-0.025em]"
                    >
                        {{
                            feedHighlight
                                ? `${feedHighlight.trainerNickname} abriu um ${feedHighlight.speciesName}`
                                : 'Nada de raro por aqui ainda'
                        }}
                    </span>
                    <span
                        class="relative text-[11px] leading-tight font-semibold text-[#7d8794] sm:text-sm"
                        >{{
                            feedHighlight
                                ? `${feedHighlight.caughtAgo} · ${formatMint(feedHighlight.mintNumber)}`
                                : 'shinies e raros do grupo aparecem aqui'
                        }}</span
                    >
                </button>
            </div>
        </div>
    </div>
</template>
