<script setup lang="ts">
import { computed } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { formatMint } from '@/lib/format';

defineOptions({ layout: EntryLayout });

/**
 * Propostas de troca pendentes, recebidas e enviadas. Só leitura por
 * enquanto: propor, aceitar e recusar são a fase 4 do SPEC.
 */
type TradeSpecimen = {
    specimenId: number;
    speciesName: string;
    spritePath: string;
    isShiny: boolean;
    mintNumber: number;
};

type TradeEntry = {
    id: number;
    otherTrainer: { nickname: string; avatarSpritePath: string | null };
    message: string | null;
    createdAgo: string;
    offered: TradeSpecimen[];
    requested: TradeSpecimen[];
};

const props = defineProps<{
    incoming: TradeEntry[];
    outgoing: TradeEntry[];
}>();

const sections = computed(() => [
    {
        key: 'incoming',
        title: 'Recebidas',
        trades: props.incoming,
        empty: 'Ninguém te propôs troca ainda.',
        // Na recebida, o outro oferece e pede o seu.
        give: (trade: TradeEntry) => trade.requested,
        get: (trade: TradeEntry) => trade.offered,
        verb: (nickname: string) => `${nickname} quer trocar com você`,
    },
    {
        key: 'outgoing',
        title: 'Enviadas',
        trades: props.outgoing,
        empty: 'Você não tem proposta esperando resposta.',
        give: (trade: TradeEntry) => trade.offered,
        get: (trade: TradeEntry) => trade.requested,
        verb: (nickname: string) => `Esperando ${nickname} responder`,
    },
]);
</script>

<template>
    <GroupScreen
        title="Trocas"
        :subtitle="
            incoming.length === 0
                ? 'Nenhuma proposta esperando você'
                : incoming.length === 1
                  ? '1 proposta esperando você'
                  : `${incoming.length} propostas esperando você`
        "
        active="trades"
    >
        <div class="flex flex-col gap-7">
            <section
                v-for="section in sections"
                :key="section.key"
                class="flex flex-col gap-3"
            >
                <h2
                    data-enter
                    class="text-muted flex items-center gap-2 text-[13.5px] font-extrabold"
                >
                    <span class="bg-brand size-3 rounded" aria-hidden="true" />
                    {{ section.title }}
                </h2>

                <p
                    v-if="section.trades.length === 0"
                    data-enter
                    class="text-soft rounded-[22px] bg-white/60 px-5 py-6 text-sm font-semibold"
                >
                    {{ section.empty }}
                </p>

                <article
                    v-for="trade in section.trades"
                    :key="trade.id"
                    data-enter
                    class="flex flex-col gap-4 rounded-[22px] bg-white p-5 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)]"
                >
                    <header class="flex items-center gap-3">
                        <TrainerAvatar
                            :sprite-path="trade.otherTrainer.avatarSpritePath"
                            size="sm"
                        />
                        <div class="flex min-w-0 flex-col">
                            <span
                                class="text-ink truncate text-[15px] font-black"
                            >
                                {{ section.verb(trade.otherTrainer.nickname) }}
                            </span>
                            <span class="text-faint text-xs font-semibold">{{
                                trade.createdAgo
                            }}</span>
                        </div>
                    </header>

                    <p
                        v-if="trade.message"
                        class="bg-field text-muted self-start rounded-[16px] px-4 py-2.5 text-sm font-semibold"
                    >
                        “{{ trade.message }}”
                    </p>

                    <div
                        class="grid grid-cols-[1fr_auto_1fr] items-center gap-3"
                    >
                        <div
                            v-for="(side, k) in [
                                {
                                    label: 'Você dá',
                                    items: section.give(trade),
                                },
                                {
                                    label: 'Você recebe',
                                    items: section.get(trade),
                                },
                            ]"
                            :key="side.label"
                            class="flex flex-col gap-2"
                            :class="k === 1 ? 'order-3' : 'order-1'"
                        >
                            <span class="text-soft text-xs font-extrabold">{{
                                side.label
                            }}</span>
                            <ul class="flex flex-wrap gap-2">
                                <li
                                    v-for="specimen in side.items"
                                    :key="specimen.specimenId"
                                    class="bg-base flex flex-col items-center rounded-[14px] px-2 pt-1 pb-2"
                                    :class="
                                        specimen.isShiny
                                            ? 'shadow-[0_0_0_2px_#4dd9e8]'
                                            : ''
                                    "
                                >
                                    <img
                                        :src="`/storage/${specimen.spritePath}`"
                                        :alt="specimen.speciesName"
                                        width="56"
                                        height="56"
                                        loading="lazy"
                                        class="pixelated size-14"
                                    />
                                    <span
                                        class="text-ink text-[11.5px] font-extrabold"
                                        >{{ specimen.speciesName }}</span
                                    >
                                    <span
                                        class="text-faint text-[10.5px] font-black"
                                        >{{
                                            formatMint(specimen.mintNumber)
                                        }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <svg
                            class="order-2 text-[#b4b9c0]"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.4"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M5 9h13M15 6l3 3-3 3" />
                            <path d="M19 15H6M9 12l-3 3 3 3" />
                        </svg>
                    </div>
                </article>
            </section>

            <p
                data-enter
                class="text-faint text-center text-[12.5px] font-semibold"
            >
                Propor, aceitar e recusar trocas chega na próxima fase.
            </p>
        </div>
    </GroupScreen>
</template>
