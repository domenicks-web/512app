<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import EntryField from '@/components/entry/EntryField.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { CYAN, IV_MAX, natureColor, STATS, TIER_META } from '@/lib/pack/meta';
import { rarityLook } from '@/lib/pack/rarity';
import type { Tier } from '@/lib/pack/types';
import { animate, spring } from '@/lib/motion';
import { store } from '@/routes/verify';
import { show as settingsShow } from '@/routes/settings';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Verificar um pack: cola as seeds e o servidor refaz a conta com o mesmo
 * código da abertura. A tela só mostra o que voltou, nunca calcula nada.
 */
type VerifiedSlot = {
    slot: number;
    seed: string;
    speciesId: number;
    dex: string;
    name: string;
    tier: Tier;
    isShiny: boolean;
    sprite: string;
    sizeClass: string;
    nature: string;
    natureEffect: string;
    natureIndex: number;
    ivs: number[];
    ivTotal: number;
};

type Verification = {
    computedHash: string;
    hashMatches: boolean | null;
    slots: VerifiedSlot[];
};

const props = defineProps<{
    prefill: {
        serverSeed: string;
        serverSeedHash: string;
        clientSeed: string;
        nonce: string;
    };
}>();

const http = useHttp({
    server_seed_hash: props.prefill.serverSeedHash,
    server_seed: props.prefill.serverSeed,
    client_seed: props.prefill.clientSeed,
    nonce: props.prefill.nonce,
});

const result = ref<Verification | null>(null);
const resultPanel = ref<HTMLElement | null>(null);
const failed = ref(false);

async function submit() {
    if (http.processing) {
        return;
    }

    failed.value = false;
    http.clearErrors();

    let response: Verification | null = null;

    try {
        response = (await http.post(store().url)) as Verification;
    } catch {
        failed.value = !http.hasErrors;
    }

    result.value = http.hasErrors ? null : response;

    if (!result.value) {
        return;
    }

    await nextTick();
    resultPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    animate(
        resultPanel.value,
        [
            { transform: 'translateY(24px)', opacity: 0 },
            { transform: 'none', opacity: 1 },
        ],
        700,
        { easing: spring(170, 16) },
    );
}

const card =
    'rounded-[22px] bg-white shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]';
</script>

<template>
    <GroupScreen
        title="Verificar"
        subtitle="Refaça a conta de um pack"
        :back="settingsShow()"
    >
        <div class="flex flex-col gap-3">
            <p
                data-enter
                :class="card"
                class="text-soft p-5 text-sm leading-relaxed font-semibold text-pretty"
            >
                Antes de abrir, o jogo mostra o
                <strong class="text-ink">hash</strong> da server seed, um lacre
                que não deixa trocar a seed depois. Ao fim do pack a seed é
                revelada. Cole os números do
                <strong class="text-ink">Comprovante</strong> aqui e o servidor
                refaz o sorteio do zero.
            </p>

            <form
                data-enter
                :class="card"
                class="flex flex-col gap-4 p-5"
                @submit.prevent="submit"
            >
                <EntryField
                    v-model="http.server_seed_hash"
                    label="Hash do servidor (opcional)"
                    name="server_seed_hash"
                    :error="http.errors.server_seed_hash"
                />
                <EntryField
                    v-model="http.server_seed"
                    label="Server seed revelada"
                    name="server_seed"
                    :error="http.errors.server_seed"
                />
                <div class="grid grid-cols-[minmax(0,1fr)_110px] gap-3">
                    <EntryField
                        v-model="http.client_seed"
                        label="Client seed"
                        name="client_seed"
                        :error="http.errors.client_seed"
                    />
                    <EntryField
                        v-model="http.nonce"
                        label="Nonce"
                        name="nonce"
                        type="number"
                        :error="http.errors.nonce"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="http.processing"
                    class="entry-button bg-brand mt-1 h-[52px] cursor-pointer rounded-full text-[15.5px] font-black text-white shadow-[0_8px_20px_rgba(242,77,77,0.3)] active:scale-[0.98] disabled:opacity-60"
                >
                    {{
                        http.processing ? 'Fazendo a conta…' : 'Refazer a conta'
                    }}
                </button>
                <p
                    v-if="failed"
                    role="alert"
                    class="text-brand-ink text-center text-[13px] font-extrabold"
                >
                    Não deu pra fazer a conta agora. Tenta de novo?
                </p>
            </form>

            <section
                v-if="result"
                ref="resultPanel"
                class="flex scroll-mt-4 flex-col gap-3"
            >
                <div
                    v-if="result.hashMatches !== null"
                    role="status"
                    class="flex flex-col gap-1.5 rounded-[22px] px-5 py-4"
                    :class="
                        result.hashMatches
                            ? 'bg-[rgba(63,143,107,0.14)] text-[#2f7a57]'
                            : 'text-brand-ink bg-[rgba(242,77,77,0.12)]'
                    "
                >
                    <span
                        class="flex items-center gap-2 text-[15.5px] font-black"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                v-if="result.hashMatches"
                                d="M5 12.5l4.5 4.5L19 7.5"
                            />
                            <path v-else d="M6 6l12 12M18 6L6 18" />
                        </svg>
                        {{
                            result.hashMatches
                                ? 'A seed bate com o hash'
                                : 'A seed não bate com o hash'
                        }}
                    </span>
                    <span class="text-[12.5px] leading-normal font-semibold">
                        {{
                            result.hashMatches
                                ? 'É a mesma seed que estava lacrada antes da abertura.'
                                : 'Essa seed gera outro hash. Confira se colou tudo certinho.'
                        }}
                    </span>
                    <span
                        v-if="!result.hashMatches"
                        class="font-mono text-[11.5px] font-bold break-all opacity-80 select-text"
                    >
                        sha256 = {{ result.computedHash }}
                    </span>
                </div>

                <ol class="flex flex-col gap-2.5">
                    <li
                        v-for="slot in result.slots"
                        :key="slot.slot"
                        :class="card"
                        class="text-ink flex flex-col gap-3 p-3 pr-4"
                    >
                        <div class="flex items-center gap-3.5">
                            <div
                                class="shrink-0 rounded-[16px] p-[2px]"
                                :style="{
                                    background: rarityLook(
                                        slot.tier,
                                        slot.isShiny,
                                    ).frame,
                                }"
                            >
                                <div
                                    class="flex size-14 items-center justify-center overflow-hidden rounded-[14px]"
                                    :style="{
                                        background: rarityLook(
                                            slot.tier,
                                            slot.isShiny,
                                        ).panel,
                                    }"
                                >
                                    <FittedSprite
                                        :src="slot.sprite"
                                        :size="56"
                                        :padding="6"
                                    />
                                </div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span
                                    class="text-faint-2 text-[11px] font-extrabold tracking-[0.14em] uppercase"
                                >
                                    Slot {{ slot.slot }} · #{{ slot.dex }}
                                </span>
                                <span
                                    class="truncate text-[15.5px] leading-tight font-black"
                                >
                                    {{ slot.name }}
                                    <span
                                        v-if="slot.isShiny"
                                        :style="{ color: CYAN }"
                                        >shiny</span
                                    >
                                </span>
                                <span class="text-soft text-xs font-semibold">
                                    {{ TIER_META[slot.tier].label }} · Tamanho
                                    {{ slot.sizeClass.toUpperCase() }} ·
                                    <span
                                        class="font-extrabold"
                                        :style="{
                                            color: natureColor(
                                                slot.natureIndex,
                                            ),
                                        }"
                                        >{{ slot.nature }}</span
                                    >
                                </span>
                            </div>
                            <div class="flex shrink-0 flex-col items-end">
                                <span class="text-[17px] font-black">{{
                                    slot.ivTotal
                                }}</span>
                                <span class="text-faint-2 text-[11px] font-bold"
                                    >IV de {{ IV_MAX }}</span
                                >
                            </div>
                        </div>
                        <div class="grid grid-cols-6 gap-1.5">
                            <div
                                v-for="(iv, index) in slot.ivs"
                                :key="index"
                                class="flex flex-col items-center rounded-xl bg-[#f3f4f6] py-1.5"
                            >
                                <span
                                    class="text-faint-2 text-[10px] font-extrabold"
                                    >{{ STATS[index] }}</span
                                >
                                <span class="text-[13px] font-black">{{
                                    iv
                                }}</span>
                            </div>
                        </div>
                        <span
                            class="text-faint-2 truncate font-mono text-[11px] font-bold select-text"
                        >
                            {{ slot.seed }}
                        </span>
                    </li>
                </ol>

                <p class="text-soft px-2 text-center text-xs font-semibold">
                    O número de série não sai da conta: ele é a ordem em que
                    cada espécie foi chegando no servidor.
                </p>
            </section>
        </div>
    </GroupScreen>
</template>
