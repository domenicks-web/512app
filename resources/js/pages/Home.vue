<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import HomeDock from '@/components/entry/HomeDock.vue';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { formatFriendsOnline, formatMint } from '@/lib/format';
import { animate, spring } from '@/lib/motion';
import { show as collectionShow } from '@/routes/collection';
import { show as rankingShow } from '@/routes/ranking';
import { index as tradesIndex } from '@/routes/trades';

defineOptions({ layout: EntryLayout });

/**
 * Home: trilho de tiles com um anel colorido que desliza até o ativo
 * (setas, hover ou clique; Enter "aperta"). Apertar Coleção, Ranking ou
 * Trocas abre a tela; o Pack de hoje só levanta a tampa até existir a
 * abertura (subprojeto C).
 */
const props = defineProps<{
    trainer: {
        nickname: string;
        tag: string;
        avatarSpritePath: string | null;
        isAdmin: boolean;
    };
    collection: { ownedSpecies: number; totalSpecies: number };
    packDate: string;
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
    1: collectionShow,
    2: rankingShow,
    3: tradesIndex,
};
const PRESS_BEFORE_VISIT = 300;

const TILE_LABELS = [
    'Pack de hoje',
    'Coleção',
    'Ranking',
    'Trocas',
    'Feed do grupo',
];

const field = useDotField();
const root = ref<HTMLElement | null>(null);
const wrap = ref<HTMLElement | null>(null);
const track = ref<HTMLElement | null>(null);
const ring = ref<HTMLElement | null>(null);
const ringLabel = ref<HTMLElement | null>(null);
const activeTile = ref(0);

const collectionPercent = computed(() =>
    props.collection.totalSpecies > 0
        ? Math.round(
              (props.collection.ownedSpecies / props.collection.totalSpecies) *
                  100,
          )
        : 0,
);

// O parceiro "olha" pro tile ativo: vira pra direita da metade em diante.
const avatarFacesRight = computed(() => activeTile.value >= 2);

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
    const shift = Math.max(
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

    // Pack de hoje: a tampa levanta. Abrir pack de verdade ainda não existe.
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

onMounted(async () => {
    await waitForIntro();
    field.hide();
    await nextTick();
    placeRing(false);

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

    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
});

const tileBase =
    'relative aspect-square w-[clamp(200px,23vw,300px)] shrink-0 cursor-pointer overflow-hidden rounded-[22px] text-left outline-none';
</script>

<template>
    <Head title="Início" />

    <div ref="root" class="absolute inset-0 z-[3] flex flex-col">
        <header class="flex items-center justify-between gap-4 px-7 pt-[22px]">
            <div data-enter="pop" class="flex items-center gap-3">
                <div
                    class="flex size-12 items-center justify-center overflow-hidden rounded-full bg-white shadow-[0_0_0_3px_#fff,0_0_0_5px_#f24d4d,0_6px_14px_rgba(28,32,38,0.12)]"
                >
                    <img
                        v-if="trainer.avatarSpritePath"
                        :src="`/storage/${trainer.avatarSpritePath}`"
                        alt=""
                        width="72"
                        height="72"
                        class="pixelated size-[72px] max-w-none transition-transform duration-[450ms] ease-[cubic-bezier(.34,1.56,.64,1)]"
                        :style="{
                            transform: avatarFacesRight ? 'scaleX(-1)' : 'none',
                        }"
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
            </div>
        </header>

        <div
            ref="wrap"
            class="flex min-h-0 flex-1 flex-col justify-center overflow-hidden pl-[clamp(24px,7vw,96px)]"
        >
            <div
                ref="track"
                role="listbox"
                aria-label="Atalhos"
                :aria-activedescendant="`home-tile-${activeTile}`"
                class="relative flex gap-[22px] pt-10 transition-transform duration-700 ease-[cubic-bezier(.34,1.3,.64,1)]"
            >
                <div
                    ref="ringLabel"
                    class="absolute top-0 left-0 flex items-center gap-2 whitespace-nowrap transition-[left] duration-[550ms] ease-[cubic-bezier(.34,1.4,.64,1)]"
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
                    class="bg-brand shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(242,77,77,0.25)]"
                    @pointerenter="activeTile = 0"
                    @focus="activeTile = 0"
                    @click="selectTile(0)"
                >
                    <span
                        class="absolute top-4 right-4 text-[12.5px] font-black tracking-[0.08em] text-white uppercase"
                    >
                        {{ packDate }}
                    </span>
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
                        class="text-brand-ink absolute bottom-4 left-4 rounded-full bg-white px-3 py-1.5 text-[12.5px] font-black"
                    >
                        1 em 512
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
                    class="flex flex-col justify-end gap-3 bg-white p-5 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)]"
                    @pointerenter="activeTile = 1"
                    @focus="activeTile = 1"
                    @click="selectTile(1)"
                >
                    <span class="flex items-baseline gap-1">
                        <span
                            class="text-ink text-[64px] leading-[0.9] font-black tracking-[-0.03em]"
                            >{{ collection.ownedSpecies }}</span
                        >
                        <span class="text-faint-2 text-[22px] font-black"
                            >/{{ collection.totalSpecies }}</span
                        >
                    </span>
                    <span
                        class="bg-base block h-2 overflow-hidden rounded-full"
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
                    class="bg-ink flex flex-col justify-end gap-1 p-5 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)]"
                    @pointerenter="activeTile = 2"
                    @focus="activeTile = 2"
                    @click="selectTile(2)"
                >
                    <span
                        class="text-[72px] leading-[0.9] font-black tracking-[-0.03em] text-white"
                        >{{
                            rankingPosition === null
                                ? '—'
                                : `${rankingPosition}º`
                        }}</span
                    >
                    <span class="text-sm font-extrabold text-[#c3cad4]">{{
                        rankingPosition === null
                            ? 'abra um pack pra entrar'
                            : 'em espécies no grupo'
                    }}</span>
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
                    class="flex flex-col justify-end gap-1 bg-white p-5 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)]"
                    @pointerenter="activeTile = 3"
                    @focus="activeTile = 3"
                    @click="selectTile(3)"
                >
                    <span
                        class="text-ink text-[72px] leading-[0.9] font-black tracking-[-0.03em]"
                        >{{ pendingTrades }}</span
                    >
                    <span class="text-soft text-sm font-extrabold">{{
                        pendingTrades === 1
                            ? 'proposta de troca'
                            : 'propostas de troca'
                    }}</span>
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
                    class="bg-screen-dark flex flex-col justify-end gap-1.5 p-5 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.16)]"
                    @pointerenter="activeTile = 4"
                    @focus="activeTile = 4"
                    @click="selectTile(4)"
                >
                    <span
                        class="absolute inset-0 bg-[radial-gradient(70%_45%_at_50%_46%,rgba(77,217,232,0.16),rgba(77,217,232,0))]"
                    />
                    <span
                        v-if="feedHighlight?.isShiny"
                        class="text-shiny relative flex h-[26px] items-center self-start rounded-full bg-[rgba(77,217,232,0.14)] px-2.5 text-xs font-extrabold tracking-[0.06em] uppercase"
                    >
                        Shiny
                    </span>
                    <span
                        class="relative text-[30px] leading-[1.05] font-black tracking-[-0.025em] text-balance text-white"
                    >
                        {{
                            feedHighlight
                                ? `${feedHighlight.trainerNickname} abriu um ${feedHighlight.speciesName}`
                                : 'Nada de raro por aqui ainda'
                        }}
                    </span>
                    <span
                        class="relative text-sm font-semibold text-[#7d8794]"
                        >{{
                            feedHighlight
                                ? `${feedHighlight.caughtAgo} · ${formatMint(feedHighlight.mintNumber)}`
                                : 'shinies e raros do grupo aparecem aqui'
                        }}</span
                    >
                </button>
            </div>
        </div>

        <div class="flex justify-center px-4 pb-[18px]">
            <div data-enter="rise">
                <HomeDock :is-admin="trainer.isAdmin" />
            </div>
        </div>

        <footer class="flex items-center px-7 pb-[22px]">
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
        </footer>
    </div>
</template>
