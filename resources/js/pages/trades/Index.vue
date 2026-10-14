<script setup lang="ts">
import { Link, router, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import TradeCeremony from '@/components/trade/TradeCeremony.vue';
import TradeOfferCard from '@/components/trade/TradeOfferCard.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import type {
    TradeCeremonyData,
    TradeOffer,
    TradeTrainer,
} from '@/lib/trade/types';
import { create as tradeCreate, index as tradesIndex } from '@/routes/trades';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Trocas: Recebidas (aceitar, contrapropor, recusar), Enviadas (cancelar;
 * vencidas e caídas esmaecidas) e Concluídas. "Nova troca" escolhe o
 * amigo e abre o montador. Recarrega junto com a presença até a 4B.
 */
const props = defineProps<{
    tab: 'recebidas' | 'enviadas' | 'concluidas';
    received: TradeOffer[];
    sent: TradeOffer[];
    done: TradeOffer[];
    friends: TradeTrainer[];
    ceremony: TradeCeremonyData | null;
    expiresAfterHours: number;
    presencePollSeconds: number;
}>();

usePoll(props.presencePollSeconds * 1000, {
    only: ['received', 'sent', 'done', 'pendingTrades'],
});

const TABS = [
    { key: 'recebidas', label: 'Recebidas' },
    { key: 'enviadas', label: 'Enviadas' },
    { key: 'concluidas', label: 'Concluídas' },
] as const;

const list = computed(
    () =>
        ({
            recebidas: props.received,
            enviadas: props.sent,
            concluidas: props.done,
        })[props.tab],
);
const empty = computed(
    () =>
        ({
            recebidas: 'Nenhuma proposta esperando você.',
            enviadas: 'Você não tem propostas enviadas.',
            concluidas: 'Nenhuma troca nos últimos dias.',
        })[props.tab],
);

const picking = ref(false);

// A cerimônia toca quando o servidor manda (troca nova aceita, ou "Ver
// resumo" de uma concluída). O poll não recarrega `ceremony`.
const showCeremony = ref(props.ceremony !== null);
watch(
    () => props.ceremony?.tradeId,
    (id) => (showCeremony.value = id !== undefined),
);

function closeCeremony() {
    showCeremony.value = false;
    router.get(
        tradesIndex.url({ query: { aba: 'concluidas' } }),
        {},
        { preserveScroll: true },
    );
}

function go(tab: string) {
    router.get(
        tradesIndex.url({ query: { aba: tab } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <GroupScreen
        title="Trocas"
        :subtitle="`${received.length} ${received.length === 1 ? 'proposta' : 'propostas'} pra você`"
    >
        <div class="flex flex-col gap-5">
            <div
                class="flex flex-wrap items-center justify-between gap-3"
                data-enter
            >
                <div
                    role="tablist"
                    class="bg-track/60 flex gap-1 rounded-full p-1"
                >
                    <button
                        v-for="option in TABS"
                        :key="option.key"
                        type="button"
                        role="tab"
                        :aria-selected="tab === option.key"
                        class="cursor-pointer rounded-full px-3 py-2 text-[13px] font-extrabold whitespace-nowrap transition-colors sm:px-4"
                        :class="
                            tab === option.key
                                ? 'text-ink bg-white shadow-[0_1px_3px_rgba(28,32,38,0.1)]'
                                : 'text-soft hover:text-ink'
                        "
                        @click="go(option.key)"
                    >
                        {{ option.label }}
                        <span
                            v-if="option.key === 'recebidas' && received.length"
                            class="bg-brand ml-1 rounded-full px-1.5 text-[11px] text-white"
                            >{{ received.length }}</span
                        >
                    </button>
                </div>
                <button
                    type="button"
                    class="bg-brand h-11 cursor-pointer rounded-full px-5 text-sm font-extrabold text-white shadow-[0_10px_24px_rgba(242,77,77,0.35)]"
                    @click="picking = !picking"
                >
                    Nova troca
                </button>
            </div>

            <div
                v-if="picking"
                class="flex flex-col gap-2 rounded-[22px] bg-white p-3 shadow-[0_8px_18px_rgba(28,32,38,0.07)]"
            >
                <span class="text-soft px-1 text-xs font-extrabold"
                    >Trocar com</span
                >
                <p
                    v-if="friends.length === 0"
                    class="text-soft px-1 text-sm font-semibold"
                >
                    Adicione amigos pra trocar.
                </p>
                <Link
                    v-for="friend in friends"
                    :key="friend.id"
                    :href="tradeCreate(friend.id)"
                    class="hover:bg-field flex items-center gap-3 rounded-2xl p-2"
                >
                    <TrainerAvatar
                        :sprite-path="friend.avatarSpritePath"
                        size="sm"
                    />
                    <span class="text-ink text-[15px] font-black">{{
                        friend.nickname
                    }}</span>
                </Link>
            </div>

            <p
                v-if="list.length === 0"
                data-enter
                class="text-soft rounded-[22px] bg-white/60 px-5 py-6 text-sm font-semibold"
            >
                {{ empty }}
            </p>
            <div class="flex flex-col gap-3">
                <TradeOfferCard
                    v-for="offer in list"
                    :key="offer.id"
                    data-enter
                    :offer="offer"
                    :expires-after-hours="expiresAfterHours"
                />
            </div>
        </div>
        <TradeCeremony
            v-if="showCeremony && ceremony"
            :key="ceremony.tradeId"
            :data="ceremony"
            @close="closeCeremony"
        />
    </GroupScreen>
</template>
