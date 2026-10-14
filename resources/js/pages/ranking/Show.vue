<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { CYAN } from '@/lib/pack/meta';
import { show as rankingShow } from '@/routes/ranking';
import { show as trainerShow } from '@/routes/trainer';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Ranking do grupo em quatro abas. Empate divide a posição; quem tem 0 no
 * critério fica no fim, sem posição. A linha do jogador vem marcada. A aba
 * é decidida no servidor: cada pílula é uma visita Inertia.
 */
type RankingTab = 'especies' | 'shinies' | 'iv' | 'primeiros';

type RankingRow = {
    userId: number;
    nickname: string;
    tag: string;
    avatarSpritePath: string | null;
    position: number | null;
    value: number;
    specimen: {
        speciesName: string;
        spritePath: string;
        isShiny: boolean;
        mintNumber: number;
    } | null;
};

const props = defineProps<{
    rows: RankingRow[];
    viewerId: number;
    tab: RankingTab;
}>();

const TABS: { key: RankingTab; label: string; unit: [string, string] }[] = [
    { key: 'especies', label: 'Espécies', unit: ['espécie', 'espécies'] },
    { key: 'shinies', label: 'Shinies', unit: ['shiny', 'shinies'] },
    { key: 'iv', label: 'Maior IV', unit: ['IV', 'IV'] },
    { key: 'primeiros', label: 'Primeiros', unit: ['primeiro', 'primeiros'] },
];

const current = computed(
    () => TABS.find((tab) => tab.key === props.tab) ?? TABS[0],
);

const viewerRow = computed(() =>
    props.rows.find((row) => row.userId === props.viewerId),
);

const subtitle = computed(() => {
    if (viewerRow.value?.position) {
        return `Você está em ${viewerRow.value.position}º em ${current.value.key === 'iv' ? 'maior IV' : current.value.label.toLowerCase()}`;
    }

    if (props.tab === 'primeiros') {
        return 'Seja o primeiro a abrir uma espécie pra entrar';
    }

    return props.tab === 'shinies'
        ? 'Tire um shiny pra entrar nesse ranking'
        : 'Abra um pack pra entrar no ranking';
});

const valueUnit = (row: RankingRow): string =>
    row.value === 1 ? current.value.unit[0] : current.value.unit[1];
</script>

<template>
    <GroupScreen title="Ranking" :subtitle="subtitle">
        <div class="flex flex-col gap-5">
            <nav
                data-enter
                aria-label="Abas do ranking"
                class="bg-track/60 flex max-w-full gap-1 self-start overflow-x-auto rounded-full p-1"
            >
                <Link
                    v-for="tab in TABS"
                    :key="tab.key"
                    :href="rankingShow.url({ query: { por: tab.key } })"
                    preserve-scroll
                    :aria-current="tab.key === props.tab ? 'page' : undefined"
                    class="rounded-full px-3 py-2 text-[13px] font-extrabold whitespace-nowrap transition-colors sm:px-4"
                    :class="
                        tab.key === props.tab
                            ? 'text-ink bg-white shadow-[0_1px_3px_rgba(28,32,38,0.1)]'
                            : 'text-soft hover:text-ink'
                    "
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <ol class="flex flex-col gap-2.5">
                <li v-for="row in rows" :key="row.userId" data-enter>
                    <Link
                        :href="trainerShow(row.userId)"
                        class="flex items-center gap-3.5 rounded-[22px] p-3 pr-5 transition-transform active:scale-[0.98]"
                        :class="
                            row.position === 1
                                ? 'bg-ink text-white shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.16)]'
                                : 'text-ink bg-white shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]'
                        "
                    >
                        <span
                            class="w-11 shrink-0 text-center text-[26px] leading-none font-black tracking-[-0.03em]"
                            :class="
                                row.position === null
                                    ? 'text-placeholder'
                                    : row.position === 1
                                      ? 'text-white'
                                      : ''
                            "
                            >{{
                                row.position === null ? '—' : `${row.position}º`
                            }}</span
                        >
                        <TrainerAvatar
                            :sprite-path="row.avatarSpritePath"
                            size="sm"
                            :highlighted="row.userId === viewerId"
                        />
                        <div class="flex min-w-0 flex-col">
                            <span class="truncate text-[15.5px] font-black">
                                {{ row.nickname }}
                                <span
                                    v-if="row.userId === viewerId"
                                    class="text-brand text-xs font-black"
                                    >você</span
                                >
                            </span>
                        </div>
                        <div class="ml-auto flex items-baseline gap-1">
                            <span
                                v-if="tab === 'iv'"
                                class="text-xs font-extrabold"
                                :class="
                                    row.position === 1
                                        ? 'text-[#c3cad4]'
                                        : 'text-soft'
                                "
                                >IV</span
                            >
                            <span
                                class="text-[28px] leading-none font-black tracking-[-0.03em]"
                                >{{ row.value }}</span
                            >
                            <span
                                v-if="tab !== 'iv'"
                                class="text-xs font-extrabold"
                                :class="
                                    row.position === 1
                                        ? 'text-[#c3cad4]'
                                        : 'text-soft'
                                "
                                >{{ valueUnit(row) }}</span
                            >
                        </div>
                        <div
                            v-if="tab === 'iv' && row.specimen"
                            class="flex w-12 shrink-0 flex-col items-center gap-0.5"
                        >
                            <FittedSprite
                                :src="`/storage/${row.specimen.spritePath}`"
                                :size="40"
                                :alt="row.specimen.speciesName"
                            />
                            <span
                                class="max-w-full truncate text-[10px] leading-tight font-extrabold"
                                :class="
                                    row.position === 1
                                        ? 'text-[#c3cad4]'
                                        : 'text-soft'
                                "
                                >{{ row.specimen.speciesName
                                }}<span
                                    v-if="row.specimen.isShiny"
                                    :style="{ color: CYAN }"
                                >
                                    shiny</span
                                ></span
                            >
                        </div>
                    </Link>
                </li>
            </ol>
        </div>
    </GroupScreen>
</template>
