<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import TradeCard from '@/components/trade/TradeCard.vue';
import TradeCountdown from '@/components/trade/TradeCountdown.vue';
import type { TradeOffer } from '@/lib/trade/types';
import { show as trainerShow } from '@/routes/trainer';
import { accept, cancel, decline, index as tradesIndex } from '@/routes/trades';
import { create as counterCreate } from '@/routes/trades/counter';

/**
 * Uma proposta na caixa: quem, a mensagem, a mesa em miniatura (você dá ⇄
 * você recebe), a validade e as ações do lado de quem vê. Recusar e
 * cancelar pedem Sim/Não na própria linha. Tocar numa carta abre a ficha.
 */
const props = defineProps<{ offer: TradeOffer; expiresAfterHours: number }>();
defineEmits<{ open: [specimenId: number] }>();

const confirming = ref(false);

const closedLabel: Record<string, string> = {
    expired: 'expirou',
    accepted: 'troca feita',
    declined: 'recusada',
};
const closedText = () =>
    props.offer.state === 'cancelled'
        ? props.offer.cancelReason === 'unavailable'
            ? 'um espécime foi trocado'
            : 'cancelada'
        : closedLabel[props.offer.state];
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-[22px] bg-white p-4 shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]"
        :class="
            offer.state === 'open' || offer.state === 'accepted'
                ? ''
                : 'opacity-60'
        "
    >
        <header class="flex items-center gap-3">
            <Link
                :href="trainerShow(offer.other.id)"
                class="flex min-w-0 flex-1 items-center gap-3"
            >
                <TrainerAvatar
                    :sprite-path="offer.other.avatarSpritePath"
                    size="sm"
                />
                <div class="flex min-w-0 flex-col">
                    <span class="text-ink truncate text-[15px] font-black">
                        {{
                            offer.direction === 'received'
                                ? `${offer.other.nickname} quer trocar`
                                : `Pra ${offer.other.nickname}`
                        }}
                    </span>
                    <span class="text-faint text-xs font-semibold">{{
                        offer.createdAgo
                    }}</span>
                </div>
            </Link>
            <span
                v-if="offer.isCounter"
                class="rounded-full bg-[#2b2f36] px-2.5 py-1 text-[10.5px] font-black tracking-[0.06em] text-white uppercase"
                >Contraproposta</span
            >
        </header>

        <p
            v-if="offer.message"
            class="text-muted rounded-2xl bg-[#f4f5f6] px-3.5 py-2.5 text-sm font-semibold"
        >
            “{{ offer.message }}”
        </p>

        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
            <div class="flex flex-col gap-1.5">
                <span class="text-soft text-[11px] font-extrabold"
                    >Você dá</span
                >
                <div class="flex flex-wrap gap-1.5">
                    <TradeCard
                        v-for="s in offer.give"
                        :key="s.specimenId"
                        :specimen="s"
                        size="sm"
                        @open="$emit('open', s.specimenId)"
                    />
                </div>
            </div>
            <span class="text-faint text-lg font-black" aria-hidden="true"
                >⇄</span
            >
            <div class="flex flex-col items-end gap-1.5">
                <span class="text-soft text-[11px] font-extrabold"
                    >Você recebe</span
                >
                <div class="flex flex-wrap justify-end gap-1.5">
                    <TradeCard
                        v-for="s in offer.receive"
                        :key="s.specimenId"
                        :specimen="s"
                        size="sm"
                        @open="$emit('open', s.specimenId)"
                    />
                </div>
            </div>
        </div>

        <footer class="flex flex-wrap items-center justify-between gap-2">
            <TradeCountdown
                v-if="offer.state === 'open'"
                :expires-at="offer.expiresAt"
                :total-hours="expiresAfterHours"
            />
            <span v-else class="text-soft text-xs font-extrabold">{{
                closedText()
            }}</span>

            <div v-if="confirming" class="flex items-center gap-2">
                <span class="text-soft text-xs font-bold">{{
                    offer.direction === 'received' ? 'Recusar?' : 'Cancelar?'
                }}</span>
                <button
                    type="button"
                    class="text-muted h-9 cursor-pointer rounded-full px-3 text-sm font-extrabold"
                    @click="confirming = false"
                >
                    Não
                </button>
                <Form
                    :action="
                        offer.direction === 'received'
                            ? decline(offer.id)
                            : cancel(offer.id)
                    "
                    #default="{ processing }"
                    @success="confirming = false"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="bg-brand h-9 cursor-pointer rounded-full px-4 text-sm font-extrabold text-white"
                    >
                        Sim
                    </button>
                </Form>
            </div>
            <div
                v-else-if="
                    offer.state === 'open' && offer.direction === 'received'
                "
                class="flex gap-2"
            >
                <button
                    type="button"
                    class="text-muted border-line-strong h-10 cursor-pointer rounded-full border-2 bg-white px-4 text-sm font-extrabold"
                    @click="confirming = true"
                >
                    Recusar
                </button>
                <Link
                    :href="counterCreate(offer.id)"
                    class="text-ink flex h-10 items-center rounded-full bg-[#eef0f2] px-4 text-sm font-extrabold"
                    >Contrapropor</Link
                >
                <Form :action="accept(offer.id)" #default="{ processing }">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="bg-brand h-10 cursor-pointer rounded-full px-5 text-sm font-extrabold text-white shadow-[0_8px_18px_rgba(242,77,77,0.35)]"
                    >
                        Aceitar
                    </button>
                </Form>
            </div>
            <button
                v-else-if="
                    offer.direction === 'sent' &&
                    (offer.state === 'open' || offer.state === 'expired')
                "
                type="button"
                class="text-muted hover:text-ink h-10 cursor-pointer rounded-full px-3 text-sm font-extrabold"
                @click="confirming = true"
            >
                {{ offer.state === 'expired' ? 'Limpar' : 'Cancelar' }}
            </button>
            <Link
                v-else-if="offer.state === 'accepted'"
                :href="tradesIndex({ query: { troca: offer.id } })"
                class="text-ink h-10 rounded-full bg-[#eef0f2] px-4 py-2.5 text-sm font-extrabold"
                >Ver resumo</Link
            >
        </footer>
    </article>
</template>
