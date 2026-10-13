<script setup lang="ts">
import { computed } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';

defineOptions({ layout: EntryLayout });

/**
 * Ranking do grupo por espécies distintas. Empate divide a posição; quem
 * ainda não tem espécie fica no fim, sem posição. A linha do jogador vem
 * marcada.
 */
type RankingRow = {
    userId: number;
    nickname: string;
    tag: string;
    avatarSpritePath: string | null;
    ownedSpecies: number;
    shinyCount: number;
    position: number | null;
};

const props = defineProps<{
    rows: RankingRow[];
    viewerId: number;
}>();

const viewerRow = computed(() =>
    props.rows.find((row) => row.userId === props.viewerId),
);

const subtitle = computed(() =>
    viewerRow.value?.position
        ? `Você está em ${viewerRow.value.position}º em espécies`
        : 'Abra um pack pra entrar no ranking',
);
</script>

<template>
    <GroupScreen title="Ranking" :subtitle="subtitle" active="ranking">
        <ol class="flex flex-col gap-2.5">
            <li
                v-for="row in rows"
                :key="row.userId"
                data-enter
                class="flex items-center gap-3.5 rounded-[22px] p-3 pr-5"
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
                    <span
                        v-if="row.shinyCount > 0"
                        class="text-shiny text-xs font-extrabold"
                        >{{ row.shinyCount }}
                        {{ row.shinyCount === 1 ? 'shiny' : 'shinies' }}</span
                    >
                </div>
                <div class="ml-auto flex items-baseline gap-1">
                    <span
                        class="text-[28px] leading-none font-black tracking-[-0.03em]"
                        >{{ row.ownedSpecies }}</span
                    >
                    <span
                        class="text-xs font-extrabold"
                        :class="
                            row.position === 1 ? 'text-[#c3cad4]' : 'text-soft'
                        "
                        >espécies</span
                    >
                </div>
            </li>
        </ol>
    </GroupScreen>
</template>
