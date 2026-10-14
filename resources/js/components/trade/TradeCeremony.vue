<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import TradeCard from '@/components/trade/TradeCard.vue';
import { Sfx } from '@/lib/pack/sfx';
import type { Tier } from '@/lib/pack/types';
import { isSoundMuted } from '@/lib/soundPreference';
import { playTradeCeremony } from '@/lib/trade/director';
import type { TradeCeremonyData } from '@/lib/trade/types';
import { show as collectionShow } from '@/routes/collection';
import { seen } from '@/routes/trades';

/**
 * Cerimônia da troca: mesa → cartas cruzando → círculo branco → resumo
 * (Você recebeu em destaque, com "Nova na Pokédex!"; Você deu menor). Um
 * toque durante a animação pula pro resumo. Tudo que aparece veio pronto
 * do servidor.
 */
const props = defineProps<{ data: TradeCeremonyData }>();
const emit = defineEmits<{ close: [] }>();

// O SSR do Inertia não injeta teleport no <body> (ver ConfirmDialog).
const isMounted = ref(false);
const root = ref<HTMLElement | null>(null);
const done = ref(false);
const sfx = new Sfx();
const TIER_ORDER: Tier[] = ['comum', 'incomum', 'raro', 'lendario'];

const best = computed(() => {
    const score = (s: { isShiny: boolean; rarityTier: Tier }) =>
        Number(s.isShiny) * 10 + TIER_ORDER.indexOf(s.rarityTier);
    const top = [...props.data.received].sort((a, b) => score(b) - score(a))[0];

    return { tier: top?.rarityTier ?? 'comum', shiny: top?.isShiny ?? false };
});

function skip() {
    if (done.value || !root.value) {
        return;
    }

    root.value.getAnimations({ subtree: true }).forEach((a) => a.finish());
    const summary = root.value.querySelector<HTMLElement>('[data-k="summary"]');

    if (summary) {
        summary.style.visibility = 'visible';
        summary.style.clipPath = 'none';
    }

    done.value = true;
}

onMounted(async () => {
    isMounted.value = true;
    sfx.on = !isSoundMuted();
    sfx.init();

    if (props.data.markSeen) {
        router.post(
            seen(props.data.tradeId).url,
            {},
            { preserveScroll: true, preserveState: true, only: [] },
        );
    }

    await nextTick();

    if (root.value) {
        await playTradeCeremony(root.value, sfx.on ? sfx : null, best.value);
    }

    done.value = true;
});

onBeforeUnmount(() => sfx.close());
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <div
            ref="root"
            class="fixed inset-0 z-[60] overflow-hidden bg-[radial-gradient(80%_70%_at_50%_50%,#2a2f3a,#12141a)] [perspective:900px]"
            role="dialog"
            aria-label="Troca feita"
            @click="skip"
        >
            <div
                data-k="row-received"
                class="absolute inset-x-0 top-[14%] flex justify-center gap-3"
            >
                <div
                    v-for="s in data.received"
                    :key="s.specimenId"
                    data-k="received"
                >
                    <TradeCard :specimen="s" size="md" />
                </div>
            </div>
            <div
                data-k="flash"
                class="absolute top-1/2 left-1/2 -mt-16 -ml-16 size-32 rounded-full bg-white opacity-0 blur-md"
            />
            <div
                data-k="row-given"
                class="absolute inset-x-0 bottom-[14%] flex justify-center gap-3"
            >
                <div v-for="s in data.given" :key="s.specimenId" data-k="given">
                    <TradeCard :specimen="s" size="md" />
                </div>
            </div>

            <section
                data-k="summary"
                class="entry-light invisible absolute inset-0 flex flex-col items-center gap-6 overflow-y-auto bg-[#eef0f2] px-5 pt-12 pb-10"
                @click.stop
            >
                <header class="flex flex-col items-center gap-2 text-center">
                    <TrainerAvatar :sprite-path="data.other.avatarSpritePath" />
                    <h2
                        class="text-ink text-[26px] font-black tracking-[-0.02em]"
                    >
                        Troca feita com {{ data.other.nickname }}
                    </h2>
                </header>

                <div class="flex flex-col items-center gap-3">
                    <span
                        class="text-soft text-xs font-extrabold tracking-[0.08em] uppercase"
                        >Você recebeu</span
                    >
                    <div class="flex flex-wrap justify-center gap-4">
                        <div
                            v-for="s in data.received"
                            :key="s.specimenId"
                            data-k="summary-card"
                            class="relative"
                        >
                            <TradeCard :specimen="s" size="lg" />
                            <span
                                v-if="data.newSpeciesIds.includes(s.speciesId)"
                                class="bg-brand absolute -top-2 left-1/2 -translate-x-1/2 rounded-full px-2.5 py-1 text-[10px] font-black tracking-[0.06em] whitespace-nowrap text-white uppercase shadow-[0_6px_14px_rgba(242,77,77,0.4)]"
                                >Nova na Pokédex!</span
                            >
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-2 opacity-70">
                    <span
                        class="text-soft text-[11px] font-extrabold tracking-[0.08em] uppercase"
                        >Você deu</span
                    >
                    <div class="flex flex-wrap justify-center gap-2">
                        <TradeCard
                            v-for="s in data.given"
                            :key="s.specimenId"
                            :specimen="s"
                            size="sm"
                        />
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap justify-center gap-3">
                    <Link
                        :href="collectionShow()"
                        class="bg-brand flex h-12 items-center rounded-full px-6 text-[15px] font-extrabold text-white shadow-[0_10px_24px_rgba(242,77,77,0.35)]"
                        >Ver na coleção</Link
                    >
                    <button
                        type="button"
                        class="text-ink h-12 cursor-pointer rounded-full bg-white px-6 text-[15px] font-extrabold shadow-[0_2px_6px_rgba(28,32,38,0.08)]"
                        @click="emit('close')"
                    >
                        Voltar pras trocas
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
