<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { formatMint } from '@/lib/format';
import { CYAN, natureColor, STATS, TIER_META } from '@/lib/pack/meta';
import { rarityLook } from '@/lib/pack/rarity';
import type { PackSpecimen } from '@/lib/pack/types';
import { show as compareShow } from '@/routes/compare';
import { show as trainerShow } from '@/routes/trainer';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Dois espécimes da mesma espécie lado a lado, como a tela de status dos
 * jogos. Quem ganha cada linha e o placar vêm decididos do servidor; a
 * tela só pinta.
 */
type Winner = 'mine' | 'theirs' | 'tie';

const props = defineProps<{
    mine: PackSpecimen;
    theirs: PackSpecimen;
    mineOwner: { nickname: string; tag: string };
    theirsOwner: { id: number; nickname: string; tag: string };
    comparison: {
        stats: { key: string; mine: number; theirs: number; winner: Winner }[];
        mint: { mine: number; theirs: number; winner: Winner };
        info: { key: 'size' | 'nature'; mine: string; theirs: string }[];
        score: { mine: number; theirs: number; total: number };
    };
    choices: {
        id: number;
        mintNumber: number;
        isShiny: boolean;
        ivTotal: number;
        isCurrent: boolean;
    }[];
}>();

const STAT_LABELS: Record<string, string> = {
    ivTotal: 'IV total',
    ivHp: STATS[0],
    ivAtk: STATS[1],
    ivDef: STATS[2],
    ivSpa: STATS[3],
    ivSpd: STATS[4],
    ivSpe: STATS[5],
};
const INFO_LABELS = { size: 'Tamanho', nature: 'Natureza' };

const sides = computed(() => [
    { key: 'mine' as const, specimen: props.mine, owner: 'Você' },
    {
        key: 'theirs' as const,
        specimen: props.theirs,
        owner: props.theirsOwner.nickname,
    },
]);

const verdict = computed(() => {
    const { mine, theirs, total } = props.comparison.score;
    const name = props.mine.name;

    if (mine > theirs) {
        return `Seu ${name} ganha em ${mine} de ${total}`;
    }

    if (theirs > mine) {
        return `O ${name} de ${props.theirsOwner.nickname} ganha em ${theirs} de ${total}`;
    }

    return `Empate: ${mine} a ${theirs}`;
});

const look = (specimen: PackSpecimen) =>
    rarityLook(specimen.tier, specimen.isShiny);

const cellClass = (winner: Winner, side: 'mine' | 'theirs') =>
    winner === side ? 'bg-[rgba(63,143,107,0.14)] text-[#2f7a57]' : 'text-ink';

const card =
    'rounded-[22px] bg-white shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]';
</script>

<template>
    <GroupScreen
        title="Comparar"
        :subtitle="mine.name"
        :back="trainerShow(theirsOwner.id)"
    >
        <div class="flex flex-col gap-3">
            <section data-enter class="grid grid-cols-2 gap-3">
                <div
                    v-for="side in sides"
                    :key="side.key"
                    :class="card"
                    class="flex min-w-0 flex-col items-center gap-2 p-3"
                >
                    <div
                        class="w-full max-w-[200px] rounded-[18px] p-[2px]"
                        :style="{
                            background: look(side.specimen).frame,
                            boxShadow: look(side.specimen).shadow,
                        }"
                    >
                        <div
                            class="flex aspect-square items-center justify-center overflow-hidden rounded-[16px]"
                            :style="{ background: look(side.specimen).panel }"
                        >
                            <FittedSprite
                                :src="side.specimen.sprite"
                                :size="120"
                                :padding="10"
                            />
                        </div>
                    </div>
                    <span
                        class="text-faint-2 text-[11px] font-extrabold tracking-[0.14em] uppercase"
                        >{{ side.owner }}</span
                    >
                    <span class="text-ink -mt-1 text-[15px] font-black"
                        >{{ formatMint(side.specimen.mint)
                        }}<span
                            v-if="side.specimen.isShiny"
                            class="ml-1"
                            :style="{ color: CYAN }"
                            >shiny</span
                        ></span
                    >
                    <span class="text-soft -mt-1 text-xs font-semibold">{{
                        TIER_META[side.specimen.tier].label
                    }}</span>
                </div>
            </section>

            <p
                data-enter
                role="status"
                class="bg-ink rounded-[22px] px-5 py-4 text-center text-[16px] font-black text-white"
            >
                {{ verdict }}
            </p>

            <section data-enter :class="card" class="flex flex-col p-2">
                <div
                    v-for="stat in comparison.stats"
                    :key="stat.key"
                    class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 border-t border-[#eceef0] px-2 py-1.5 first:border-t-0"
                    :class="{ 'pb-3': stat.key === 'ivTotal' }"
                >
                    <span
                        class="rounded-xl py-1.5 text-center text-[17px] font-black"
                        :class="cellClass(stat.winner, 'mine')"
                        >{{ stat.winner === 'mine' ? '▲ ' : ''
                        }}{{ stat.mine }}</span
                    >
                    <span
                        class="text-soft w-16 text-center text-xs font-extrabold"
                        >{{ STAT_LABELS[stat.key] }}</span
                    >
                    <span
                        class="rounded-xl py-1.5 text-center text-[17px] font-black"
                        :class="cellClass(stat.winner, 'theirs')"
                        >{{ stat.winner === 'theirs' ? '▲ ' : ''
                        }}{{ stat.theirs }}</span
                    >
                </div>
            </section>

            <section data-enter :class="card" class="flex flex-col p-2">
                <div
                    class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 px-2 py-1.5"
                >
                    <span
                        class="rounded-xl py-1.5 text-center font-mono text-[15px] font-black"
                        :class="cellClass(comparison.mint.winner, 'mine')"
                        >{{ formatMint(comparison.mint.mine) }}</span
                    >
                    <span
                        class="text-soft w-16 text-center text-xs font-extrabold"
                        >Nº de série</span
                    >
                    <span
                        class="rounded-xl py-1.5 text-center font-mono text-[15px] font-black"
                        :class="cellClass(comparison.mint.winner, 'theirs')"
                        >{{ formatMint(comparison.mint.theirs) }}</span
                    >
                </div>
                <div
                    v-for="row in comparison.info"
                    :key="row.key"
                    class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 border-t border-[#eceef0] px-2 py-2.5"
                >
                    <span
                        class="text-ink text-center text-[14px] font-black"
                        :style="
                            row.key === 'nature'
                                ? { color: natureColor(mine.natureIndex) }
                                : undefined
                        "
                        >{{ row.mine }}</span
                    >
                    <span
                        class="text-soft w-16 text-center text-xs font-extrabold"
                        >{{ INFO_LABELS[row.key] }}</span
                    >
                    <span
                        class="text-ink text-center text-[14px] font-black"
                        :style="
                            row.key === 'nature'
                                ? { color: natureColor(theirs.natureIndex) }
                                : undefined
                        "
                        >{{ row.theirs }}</span
                    >
                </div>
            </section>

            <section
                v-if="choices.length > 1"
                data-enter
                class="flex flex-col gap-2"
            >
                <span class="text-soft px-1 text-[13px] font-extrabold"
                    >Comparar outro {{ mine.name }} seu</span
                >
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="choice in choices"
                        :key="choice.id"
                        :href="
                            compareShow({ mine: choice.id, theirs: theirs.id })
                        "
                        preserve-scroll
                        :aria-current="choice.isCurrent ? 'true' : undefined"
                        class="rounded-full px-3.5 py-2 text-[13px] font-extrabold transition-transform active:scale-[0.97]"
                        :class="
                            choice.isCurrent
                                ? 'bg-ink text-white'
                                : 'text-ink bg-white shadow-[0_1px_3px_rgba(28,32,38,0.1)]'
                        "
                        >{{ formatMint(choice.mintNumber) }} · IV
                        {{ choice.ivTotal
                        }}<span
                            v-if="choice.isShiny"
                            class="ml-1"
                            :style="{ color: CYAN }"
                            >shiny</span
                        ></Link
                    >
                </div>
            </section>
        </div>
    </GroupScreen>
</template>
