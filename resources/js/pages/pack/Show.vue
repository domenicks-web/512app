<script setup lang="ts">
import { isSoundMuted, setSoundMuted } from '@/lib/soundPreference';
import { Head, Link } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
} from 'vue';
import FairnessReceipt from '@/components/pack/FairnessReceipt.vue';
import MiniBall from '@/components/pack/MiniBall.vue';
import PackSummary from '@/components/pack/PackSummary.vue';
import SpecimenSheet from '@/components/pack/SpecimenSheet.vue';
import { PackDirector } from '@/lib/pack/director';
import type { PackApi, PackSceneState } from '@/lib/pack/director';
import {
    barColor,
    dayMonth,
    isGoldMint,
    IV_MAX,
    natureColor,
    pad,
    shortHash,
    sizeLabel,
    sizeShort,
    spriteOf,
    TIER_META,
} from '@/lib/pack/meta';
import { vPixelFit } from '@/lib/pack/pixelFit';
import { Sfx } from '@/lib/pack/sfx';
import type { PackOpening, PackSpecimen, Receipt } from '@/lib/pack/types';
import { prefersReducedMotion } from '@/lib/motion';
import { home } from '@/routes';
import { open as openRoute, reveal as revealRoute } from '@/routes/pack';
import { update as updateClientSeed } from '@/routes/pack/client-seed';

/**
 * Abertura do pack (handoff `ABERTURA.md` + `512 Abertura`). O sorteio é
 * no servidor; esta tela só encena. A explosão grava o pack, e cada bola
 * pergunta o próprio conteúdo ao servidor quando é clicada.
 */
const props = defineProps<{
    serverSeedHash: string;
    clientSeed: string;
    clientSeedMaxLength: number;
    nextPackAt: string | null;
    guaranteedSlot: number;
    opening: PackOpening | null;
}>();

const SLOT_COUNT = 5;

/**
 * Celular: o "5 do dado" (decidido com o usuário). Slots 1–4 nos cantos e
 * o garantido no centro. Tudo em classes do Tailwind: o CSS de bloco
 * `<style>` chega pelo JS no dev e já falhou no celular.
 */
const SLOT_MOBILE_AREA = [
    'max-md:[grid-area:1/1]',
    'max-md:[grid-area:1/3]',
    'max-md:[grid-area:3/1]',
    'max-md:[grid-area:3/3]',
    'max-md:[grid-area:2/2]',
];

const root = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);

const initialPhase = (): PackSceneState['phase'] => {
    if (!props.opening) {
        return 'idle';
    }

    return props.opening.isComplete ? 'summary' : 'balls';
};

const scene = reactive<PackSceneState>({
    phase: initialPhase(),
    slots: Array.from({ length: SLOT_COUNT }, (_, i) => ({
        slot: i + 1,
        specimen: props.opening?.slots[i]?.specimen ?? null,
        pending: false,
    })),
    openingId: props.opening?.id ?? null,
    ceremony: null,
    locked: false,
    showOpenAll: false,
    receipt: props.opening?.receipt ?? null,
    nextPackAt: props.nextPackAt,
    error: null,
});

const openedAt = ref(props.opening?.createdAt ?? new Date().toISOString());
const clientSeed = ref(props.clientSeed);
const savedClientSeed = ref(props.clientSeed);
const muted = ref(false);
const sheetIndex = ref<number | null>(null);
const receiptOpen = ref(false);
const isMobile = ref(false);
const today = dayMonth(new Date());

const sfx = new Sfx();
let director: PackDirector | null = null;
let mobileQuery: MediaQueryList | null = null;

const specimens = computed(() =>
    scene.slots
        .map((slot) => slot.specimen)
        .filter((s): s is PackSpecimen => s !== null),
);
const remaining = computed(
    () => scene.slots.filter((slot) => !slot.specimen).length,
);
const ballsHint = computed(() => {
    if (remaining.value === 0) {
        return '';
    }

    return remaining.value === SLOT_COUNT
        ? 'Toque nas bolas para abrir'
        : `Faltam ${remaining.value}`;
});
const cer = computed(() => scene.ceremony);
const sheetSpecimen = computed(() =>
    sheetIndex.value === null
        ? null
        : (specimens.value[sheetIndex.value] ?? null),
);

/** Sprite do slot: no celular os 4 dos cantos até 2×, o garantido (centro) até 3×; no desktop até 3×. */
function slotFit(index: number) {
    if (!isMobile.value) {
        return { cap: 3, maxH: 160 };
    }

    return index === SLOT_COUNT - 1
        ? { cap: 3, maxH: 140 }
        : { cap: 2, maxH: 112 };
}

const ceremonyFit = computed(() => ({
    cap: 4,
    min: 2,
    maxW: 340,
    maxH: Math.min(
        300,
        typeof window === 'undefined' ? 300 : window.innerHeight * 0.48 - 60,
    ),
}));

// ── servidor ──────────────────────────────────────────────────────────

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function request<T>(
    method: string,
    url: string,
    body?: unknown,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = (await response.json().catch(() => ({}))) as T & {
        message?: string;
    };

    if (!response.ok) {
        throw new Error(
            data.message ?? 'Algo deu errado. Recarregue a página.',
        );
    }

    return data;
}

const api: PackApi = {
    openPack: () =>
        request<{ openingId: number; nextPackAt: string | null }>(
            'POST',
            openRoute.url(),
        ),
    reveal: (openingId, slot) =>
        request<{
            specimen: PackSpecimen;
            isComplete: boolean;
            receipt: Receipt | null;
        }>('POST', revealRoute.url({ opening: openingId, slot })),
};

async function saveClientSeed() {
    const value = clientSeed.value
        .replace(/\s/g, '')
        .slice(0, props.clientSeedMaxLength);

    if (!value || value === savedClientSeed.value) {
        clientSeed.value = savedClientSeed.value;

        return;
    }

    try {
        await request('PATCH', updateClientSeed.url(), { client_seed: value });
        savedClientSeed.value = value;
        clientSeed.value = value;
    } catch {
        clientSeed.value = savedClientSeed.value;
    }
}

function onClientSeedInput(event: Event) {
    const input = event.target as HTMLInputElement;
    clientSeed.value = input.value
        .replace(/\s/g, '')
        .slice(0, props.clientSeedMaxLength);
    input.value = clientSeed.value;
}

// ── interação ─────────────────────────────────────────────────────────

function pressBall(event: PointerEvent) {
    event.preventDefault();
    event.stopPropagation();
    director?.startCharge();
}

function toggleMute(event: Event) {
    event.stopPropagation();
    sfx.init();
    muted.value = !muted.value;
    sfx.on = !muted.value;

    if (muted.value) {
        sfx.rumble(0);
    }

    setSoundMuted(muted.value);
}

function reload() {
    window.location.reload();
}

onMounted(async () => {
    muted.value = isSoundMuted();

    sfx.on = !muted.value;
    mobileQuery = window.matchMedia('(max-width: 767.98px)');
    isMobile.value = mobileQuery.matches;
    mobileQuery.addEventListener('change', onMobileChange);
    await nextTick();

    if (!root.value || !canvas.value) {
        return;
    }

    director = new PackDirector(
        root.value,
        canvas.value,
        scene,
        api,
        sfx,
        prefersReducedMotion(),
    );

    if (scene.phase === 'idle') {
        director.introIdle();
    } else if (scene.phase === 'balls') {
        director.resumeBalls();
    } else {
        director.showSummaryNow();
    }
});

function onMobileChange() {
    isMobile.value = mobileQuery?.matches ?? false;
}

onBeforeUnmount(() => {
    director?.destroy();
    sfx.close();
    mobileQuery?.removeEventListener('change', onMobileChange);
});
</script>

<template>
    <Head title="Pack de hoje" />

    <div
        ref="root"
        class="pack-screen fixed inset-0 overflow-hidden bg-[#1c1f26] font-sans text-white select-none"
        @click="director?.tap()"
    >
        <div
            data-k="glowA"
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_45%_at_50%_46%,rgba(242,77,77,0.16),rgba(242,77,77,0)_100%)]"
        />
        <div
            data-k="glowB"
            class="pointer-events-none absolute inset-0 opacity-0"
        />
        <div
            data-k="chargeGlow"
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(38%_30%_at_50%_47%,rgba(242,77,77,0.42),rgba(242,77,77,0)_100%)] opacity-0"
        />

        <div data-k="stage" class="absolute inset-0">
            <!-- Idle: a bola grande, segurar pra abrir. -->
            <div
                v-if="scene.phase === 'idle' || scene.phase === 'run'"
                data-k="idle"
                class="absolute inset-0 box-border flex flex-col items-center justify-between gap-5 px-5 pt-[84px] pb-[6vh]"
            >
                <div class="flex flex-col items-center gap-2.5 text-center">
                    <div
                        class="text-[11px] font-extrabold tracking-[0.2em] text-[#7d8794] uppercase"
                    >
                        Pack de hoje · {{ today }}
                    </div>
                    <h1
                        class="text-[clamp(30px,5vw,44px)] leading-[1.05] font-black tracking-[-0.035em]"
                    >
                        Cinco espécimes
                    </h1>
                    <div class="text-sm font-semibold text-[#7d8794]">
                        O último é incomum ou melhor
                    </div>
                </div>

                <div
                    class="flex min-h-0 w-full flex-auto items-center justify-center"
                >
                    <div
                        class="relative aspect-square w-[min(210px,50vw,30vh)]"
                    >
                        <button
                            type="button"
                            data-k="ballWrap"
                            class="absolute inset-0 cursor-pointer touch-none rounded-full outline-none focus-visible:shadow-[0_0_0_4px_rgba(242,77,77,0.5)]"
                            aria-label="Segure para abrir o pack"
                            @pointerdown="pressBall"
                        >
                            <div
                                data-k="ballShadow"
                                class="absolute right-[16%] -bottom-[17%] left-[16%] h-[9%] rounded-[50%] bg-black/50 blur-[9px]"
                            />
                            <svg
                                viewBox="0 0 100 100"
                                class="pointer-events-none absolute -inset-[17%] h-[134%] w-[134%] -rotate-90 overflow-visible"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="50"
                                    cy="50"
                                    r="48"
                                    fill="none"
                                    stroke="rgba(255,255,255,0.07)"
                                    stroke-width="1.2"
                                />
                                <circle
                                    data-k="ringFill"
                                    cx="50"
                                    cy="50"
                                    r="48"
                                    fill="none"
                                    stroke="#f24d4d"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-dasharray="301.6"
                                    stroke-dashoffset="301.6"
                                />
                            </svg>
                            <div
                                data-k="ballShake"
                                class="absolute inset-0 origin-[50%_85%]"
                            >
                                <div
                                    data-k="bTop"
                                    class="absolute top-0 left-0 h-1/2 w-full origin-[10%_100%] overflow-hidden rounded-t-full bg-[#f24d4d]"
                                >
                                    <div
                                        class="absolute inset-0 bg-[radial-gradient(140%_120%_at_72%_115%,rgba(110,10,20,0.28),rgba(110,10,20,0)_60%)]"
                                    />
                                    <div
                                        class="absolute top-[26%] left-[18%] h-[15%] w-1/4 -rotate-30 rounded-full bg-[#ffd9d9] opacity-75"
                                    />
                                </div>
                                <div
                                    data-k="bBot"
                                    class="absolute top-1/2 left-0 z-[2] h-1/2 w-full origin-[10%_0%] rounded-b-full bg-[#f4f5f7]"
                                >
                                    <div
                                        class="absolute inset-0 rounded-[inherit] bg-[radial-gradient(130%_120%_at_60%_125%,rgba(28,31,38,0.26),rgba(28,31,38,0)_62%)]"
                                    />
                                    <div
                                        class="absolute -top-[8%] right-0 left-0 h-[16%] bg-[#434a53]"
                                    />
                                    <div
                                        class="absolute top-0 left-1/2 flex aspect-square w-[34%] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-[#434a53]"
                                    >
                                        <div
                                            data-k="bBtn"
                                            class="h-[56%] w-[56%] rounded-full bg-[#e4e7eb] shadow-[inset_0_-3px_0_rgba(0,0,0,0.12)]"
                                        />
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-4">
                    <div class="text-[15px] font-extrabold text-[#c3cad4]">
                        Segure a bola para abrir
                    </div>
                    <div
                        class="flex w-full max-w-[min(440px,100%)] flex-col items-stretch gap-2 sm:w-auto sm:max-w-none sm:flex-row sm:items-center sm:justify-center"
                    >
                        <div
                            title="sha256(server_seed), gravado antes do sorteio"
                            class="flex h-8 items-center justify-center gap-2 rounded-full bg-white/6 px-3 text-xs font-semibold whitespace-nowrap text-[#7d8794]"
                        >
                            <svg
                                width="12"
                                height="12"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.6"
                                stroke-linecap="round"
                                aria-hidden="true"
                            >
                                <rect
                                    x="5"
                                    y="11"
                                    width="14"
                                    height="10"
                                    rx="3"
                                />
                                <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                            </svg>
                            Hash do servidor
                            <span
                                class="font-mono font-extrabold text-[#c3cad4]"
                                >{{ shortHash(serverSeedHash) }}</span
                            >
                        </div>
                        <label
                            title="Você pode trocar sua client seed antes de abrir"
                            class="flex h-8 min-w-0 cursor-text items-center gap-2 rounded-full bg-white/6 pr-1.5 pl-3 text-xs font-semibold whitespace-nowrap text-[#7d8794] sm:max-w-[360px]"
                            @pointerdown.stop
                            @click.stop
                        >
                            Client seed
                            <input
                                :value="clientSeed"
                                :maxlength="clientSeedMaxLength"
                                :size="
                                    Math.max(
                                        6,
                                        Math.min(clientSeed.length + 1, 25),
                                    )
                                "
                                spellcheck="false"
                                autocomplete="off"
                                class="h-[26px] min-w-0 flex-1 truncate rounded-full border-0 bg-transparent px-1.5 font-mono text-xs font-extrabold text-[#c3cad4] outline-none"
                                @input="onClientSeedInput"
                                @blur="saveClientSeed"
                                @keydown.enter="
                                    ($event.target as HTMLInputElement).blur()
                                "
                            />
                            <svg
                                width="11"
                                height="11"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="mr-1.5"
                                aria-hidden="true"
                            >
                                <path d="M4 20h4L19 9l-4-4L4 16z" />
                            </svg>
                        </label>
                    </div>
                </div>
            </div>

            <!-- As 5 bolas no chão. -->
            <div
                v-if="
                    scene.phase === 'balls' ||
                    scene.phase === 'run' ||
                    scene.phase === 'summary'
                "
                data-k="slots"
                class="absolute bottom-[10vh] left-1/2 w-[min(880px,94vw)] -translate-x-1/2 opacity-0 max-md:top-[54%] max-md:bottom-auto max-md:w-[min(420px,94vw)] max-md:-translate-y-1/2"
            >
                <div
                    class="grid grid-cols-5 gap-2 max-md:grid-cols-3 max-md:gap-x-2.5 max-md:gap-y-1.5"
                >
                    <div
                        v-for="(slot, index) in scene.slots"
                        :key="slot.slot"
                        :data-slot="index"
                        class="relative flex min-w-0 flex-col items-center gap-3"
                        :class="SLOT_MOBILE_AREA[index]"
                    >
                        <div
                            data-k="sbox"
                            class="relative flex h-[168px] w-full items-end justify-center"
                            :class="
                                slot.slot === guaranteedSlot
                                    ? 'max-md:h-[150px]'
                                    : 'max-md:h-[120px]'
                            "
                        >
                            <div
                                class="absolute right-[24%] -bottom-[5px] left-[24%] h-2.5 rounded-[50%] bg-black/40 blur-[4px]"
                            />
                            <img
                                v-if="slot.specimen"
                                v-pixel-fit="slotFit(index)"
                                data-k="sspr"
                                :src="spriteOf(slot.specimen)"
                                :data-fallback="slot.specimen.sprite"
                                :alt="slot.specimen.name"
                                draggable="false"
                                class="relative block origin-[50%_100%] opacity-0 [image-rendering:pixelated]"
                            />
                            <button
                                type="button"
                                data-ball
                                class="absolute bottom-[-9px] left-1/2 flex min-h-16 min-w-16 -translate-x-1/2 items-end justify-center outline-none focus-visible:rounded-full focus-visible:shadow-[0_0_0_3px_rgba(242,77,77,0.6)]"
                                :class="
                                    slot.specimen
                                        ? 'pointer-events-none'
                                        : 'cursor-pointer'
                                "
                                :disabled="
                                    slot.specimen !== null || scene.locked
                                "
                                :aria-label="`Abrir bola ${slot.slot}`"
                                :tabindex="slot.specimen ? -1 : 0"
                                @click.stop="director?.openSlot(index)"
                            >
                                <div
                                    data-k="mball"
                                    class="relative mb-[9px] size-14 opacity-0 max-md:size-[52px]"
                                >
                                    <MiniBall
                                        :keys="{ shake: 'mShake', top: 'mTop' }"
                                    />
                                </div>
                            </button>
                        </div>
                        <div
                            data-k="slabel"
                            class="flex max-w-full flex-col items-center gap-1.5 opacity-0"
                        >
                            <div
                                class="max-w-full truncate text-sm font-extrabold"
                            >
                                {{ slot.specimen?.name ?? '' }}
                            </div>
                            <div
                                class="h-1 w-[22px] rounded-full"
                                :style="{
                                    background: slot.specimen
                                        ? barColor(slot.specimen)
                                        : 'transparent',
                                }"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-7 flex min-h-9 flex-col items-center gap-3">
                    <div
                        v-if="ballsHint && scene.phase === 'balls'"
                        class="text-[15px] font-extrabold text-[#c3cad4]"
                        aria-live="polite"
                    >
                        {{ ballsHint }}
                    </div>
                    <button
                        v-if="
                            scene.showOpenAll && !scene.locked && remaining > 0
                        "
                        type="button"
                        class="h-9 cursor-pointer rounded-full bg-white/8 px-4 text-[13px] font-extrabold text-[#c3cad4] hover:bg-white/12"
                        @click.stop="director?.openAll()"
                    >
                        Abrir todas
                    </button>
                </div>
            </div>

            <div
                data-k="dark"
                class="pointer-events-none absolute inset-0 bg-black opacity-0"
            />

            <!-- Cerimônia (raro, lendário, shiny). -->
            <div data-k="cer" class="pointer-events-none absolute inset-0">
                <div
                    data-k="rays"
                    class="absolute top-[calc(48%-90px)] left-1/2 -mt-[600px] -ml-[600px] size-[1200px] rounded-full bg-[repeating-conic-gradient(from_0deg,rgba(255,201,77,0.2)_0deg_5deg,rgba(255,201,77,0)_5deg_15deg)] opacity-0"
                />
                <div
                    data-k="beam"
                    class="absolute top-0 left-1/2 -ml-[150px] h-[calc(48%+40px)] w-[300px] origin-[50%_80%] opacity-0"
                />
                <div
                    data-k="halo"
                    class="absolute top-[calc(48%-90px)] left-1/2 -mt-[260px] -ml-[260px] size-[520px] rounded-full opacity-0"
                />

                <div
                    data-k="cball"
                    class="absolute top-[calc(48%-70px)] left-1/2 -mt-[66px] -ml-[66px] size-[132px] opacity-0"
                >
                    <MiniBall
                        shine
                        :band="16"
                        :button="34"
                        :keys="{ shake: 'cShake', top: 'cTop', btn: 'cBtn' }"
                    />
                </div>

                <div
                    class="absolute top-[48%] right-0 left-0 flex h-0 items-end justify-center"
                >
                    <div
                        data-k="cspr"
                        class="relative origin-[50%_100%] opacity-0"
                    >
                        <div data-k="cfloat" class="relative">
                            <img
                                v-if="cer"
                                v-pixel-fit="ceremonyFit"
                                data-k="cimg"
                                :src="spriteOf(cer)"
                                :data-fallback="cer.sprite"
                                alt=""
                                draggable="false"
                                class="block [image-rendering:pixelated]"
                            />
                            <div
                                data-k="cshine"
                                class="pointer-events-none absolute inset-0 bg-[linear-gradient(110deg,rgba(255,255,255,0)_42%,rgba(255,255,255,0.95)_50%,rgba(255,255,255,0)_58%)] bg-[length:320%_100%] bg-[position:100%_0] [mask-size:100%_100%] [mask-repeat:no-repeat] opacity-0"
                            />
                        </div>
                    </div>
                </div>

                <div
                    data-k="cinfo"
                    class="absolute top-[calc(48%+26px)] right-0 left-0 flex flex-col items-center gap-3.5 px-5 text-center"
                >
                    <div
                        data-k="cname"
                        class="flex flex-wrap justify-center text-[clamp(42px,8vw,76px)] leading-none font-black tracking-[-0.035em]"
                    >
                        <span
                            v-for="(letter, k) in cer
                                ? Array.from(cer.name)
                                : []"
                            :key="k"
                            data-l
                            class="inline-block whitespace-pre opacity-0"
                            >{{ letter }}</span
                        >
                    </div>
                    <div
                        v-if="cer"
                        data-k="cchips"
                        class="flex flex-nowrap justify-center gap-2 whitespace-nowrap opacity-0"
                    >
                        <span
                            class="flex h-7 items-center rounded-full px-3 text-xs font-extrabold"
                            :style="{
                                background: TIER_META[cer.tier].color,
                                color: TIER_META[cer.tier].fg,
                            }"
                            >{{ TIER_META[cer.tier].label }}</span
                        >
                        <span
                            v-if="cer.isShiny"
                            class="flex h-7 items-center gap-1.5 rounded-full bg-[#4dd9e8] px-3 text-xs font-extrabold text-[#1c1f26]"
                        >
                            <svg
                                width="11"
                                height="11"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 1l2.6 8.4L23 12l-8.4 2.6L12 23l-2.6-8.4L1 12l8.4-2.6z"
                                /></svg
                            >Shiny
                        </span>
                        <span
                            class="flex h-7 items-center rounded-full px-3 font-mono text-xs font-extrabold"
                            :style="{
                                background: isGoldMint(cer)
                                    ? '#FFC94D'
                                    : 'rgba(255,255,255,0.1)',
                                color: isGoldMint(cer) ? '#2b2f36' : '#c3cad4',
                            }"
                            >Nº {{ pad(cer.mint, 4) }}</span
                        >
                    </div>
                    <div
                        v-if="cer"
                        data-k="cmeta"
                        class="flex w-full max-w-[440px] flex-col items-center gap-2.5 opacity-0"
                    >
                        <div
                            v-if="cer.mint === 1"
                            class="flex h-7 items-center gap-1.5 rounded-full bg-[rgba(255,201,77,0.14)] px-3 text-xs font-extrabold text-[#FFC94D]"
                        >
                            <svg
                                width="11"
                                height="11"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 2l2.9 6.6 7.1.6-5.4 4.7 1.6 7L12 17.3 5.8 20.9l1.6-7L2 9.2l7.1-.6z"
                                />
                            </svg>
                            Primeiro {{ cer.name }} do servidor
                        </div>
                        <dl class="grid w-full grid-cols-3 gap-2">
                            <div
                                class="flex min-w-0 flex-col items-center gap-0.5 rounded-2xl bg-[#262a33] px-2 py-2.5 shadow-[0_0_0_1px_rgba(255,255,255,0.06)]"
                            >
                                <dt
                                    class="text-[10px] font-extrabold tracking-[0.14em] text-[#7d8794] uppercase"
                                >
                                    Tamanho
                                </dt>
                                <dd class="text-base font-black text-white">
                                    {{ sizeLabel(cer) }}
                                </dd>
                                <dd
                                    class="text-[11px] leading-tight font-semibold text-[#7d8794]"
                                >
                                    {{ sizeShort(cer) }}
                                </dd>
                            </div>
                            <div
                                class="flex min-w-0 flex-col items-center gap-0.5 rounded-2xl bg-[#262a33] px-2 py-2.5 shadow-[0_0_0_1px_rgba(255,255,255,0.06)]"
                            >
                                <dt
                                    class="text-[10px] font-extrabold tracking-[0.14em] text-[#7d8794] uppercase"
                                >
                                    IV
                                </dt>
                                <dd class="text-base font-black text-white">
                                    {{ cer.ivTotal }}
                                </dd>
                                <dd
                                    class="text-[11px] leading-tight font-semibold text-[#7d8794]"
                                >
                                    de {{ IV_MAX }}
                                </dd>
                            </div>
                            <div
                                class="flex min-w-0 flex-col items-center gap-0.5 rounded-2xl bg-[#262a33] px-2 py-2.5 shadow-[0_0_0_1px_rgba(255,255,255,0.06)]"
                            >
                                <dt
                                    class="text-[10px] font-extrabold tracking-[0.14em] text-[#7d8794] uppercase"
                                >
                                    Natureza
                                </dt>
                                <dd
                                    class="flex max-w-full items-center gap-1.5 truncate text-base font-black text-white"
                                >
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :style="{
                                            background: natureColor(
                                                cer.natureIndex,
                                            ),
                                        }"
                                    />{{ cer.nature }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div
                        data-k="cnext"
                        class="mt-2 text-[11px] font-extrabold tracking-[0.18em] text-[#7d8794] uppercase opacity-0"
                    >
                        Toque para continuar
                    </div>
                </div>
            </div>
        </div>

        <canvas
            ref="canvas"
            data-k="fx"
            class="pointer-events-none absolute inset-0 h-full w-full"
            aria-hidden="true"
        />
        <div
            data-k="black"
            class="pointer-events-none absolute inset-0 bg-black opacity-0"
        />
        <div
            data-k="flash"
            class="pointer-events-none absolute inset-0 bg-white opacity-0"
        />

        <!-- Resumo (tema claro), revelado com a transição circular. -->
        <div
            v-if="scene.phase === 'summary' && specimens.length === SLOT_COUNT"
            data-k="sum"
            class="invisible absolute inset-0 z-10 overflow-x-hidden overflow-y-auto bg-[#eff0f2] text-[#2b2f36] [clip-path:circle(0%_at_50%_85%)]"
        >
            <PackSummary
                :specimens="specimens"
                :opened-at="openedAt"
                :next-pack-at="scene.nextPackAt"
                @sheet="sheetIndex = $event"
                @receipt="receiptOpen = true"
            />
        </div>

        <!-- Topo: logo (volta pro jogo) e mudo. -->
        <div
            class="pointer-events-none absolute top-0 right-0 left-0 z-20 flex items-center justify-between px-5 py-[18px]"
        >
            <Link
                :href="home.url()"
                class="pointer-events-auto block drop-shadow-[0_6px_14px_rgba(242,77,77,0.28)]"
                aria-label="Voltar ao jogo"
                @click.stop
            >
                <img
                    src="/logo.svg"
                    alt=""
                    width="76"
                    height="38"
                    class="h-[38px] w-auto"
                />
            </Link>
            <button
                type="button"
                class="pointer-events-auto flex size-10 cursor-pointer items-center justify-center rounded-full"
                :class="
                    scene.phase === 'summary'
                        ? 'bg-white text-[#2b2f36] shadow-[0_2px_6px_rgba(28,32,38,0.08)]'
                        : 'bg-white/8 text-[#c3cad4]'
                "
                :aria-label="muted ? 'Ligar som' : 'Desligar som'"
                @click="toggleMute"
            >
                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M4 9h4l5-4v14l-5-4H4z" />
                    <template v-if="!muted">
                        <path d="M16.5 8.5a5 5 0 0 1 0 7" />
                        <path d="M19 6a8.5 8.5 0 0 1 0 12" />
                    </template>
                    <path v-else d="M17 9.5l5 5M22 9.5l-5 5" />
                </svg>
            </button>
        </div>

        <div
            v-if="scene.error"
            role="alert"
            class="absolute top-20 left-1/2 z-40 flex w-max max-w-[min(440px,calc(100vw-32px))] -translate-x-1/2 items-center gap-3 rounded-[22px] bg-white py-3 pr-3 pl-4 text-sm font-extrabold text-[#2b2f36] shadow-[0_12px_30px_rgba(0,0,0,0.35)]"
            @click.stop
        >
            {{ scene.error }}
            <button
                type="button"
                class="h-8 shrink-0 cursor-pointer rounded-full bg-[#f24d4d] px-3 text-xs font-extrabold text-white"
                @click="reload"
            >
                Recarregar
            </button>
        </div>

        <SpecimenSheet :specimen="sheetSpecimen" @close="sheetIndex = null" />
        <FairnessReceipt
            :open="receiptOpen"
            :receipt="scene.receipt"
            :specimens="specimens"
            @close="receiptOpen = false"
        />
    </div>
</template>
