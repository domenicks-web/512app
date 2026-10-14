<script setup lang="ts">
import FittedSprite from '@/components/FittedSprite.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { formatMint } from '@/lib/format';
import { rarityLook } from '@/lib/pack/rarity';
import type { Tier } from '@/lib/pack/types';

defineOptions({ layout: EntryLayout });

/**
 * Feed do grupo: as aberturas boas mais recentes (todo shiny, mais raros e
 * lendários), de quem abriu, com a moldura da raridade no sprite.
 */
type FeedEntry = {
    id: number;
    isShiny: boolean;
    trainerNickname: string;
    trainerAvatarSpritePath: string | null;
    speciesName: string;
    rarityTier: string;
    spritePath: string;
    mintNumber: number;
    caughtAgo: string;
};

defineProps<{
    entries: FeedEntry[];
}>();

const look = (entry: FeedEntry) =>
    rarityLook(entry.rarityTier as Tier, entry.isShiny);
</script>

<template>
    <GroupScreen title="Feed" subtitle="Shinies e raros do grupo">
        <p
            v-if="entries.length === 0"
            data-enter
            class="text-soft rounded-[22px] bg-white p-6 text-center text-sm font-semibold shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]"
        >
            Nada de raro por aqui ainda. Quando alguém abrir um shiny, um raro
            ou um lendário, aparece aqui.
        </p>

        <ol v-else class="flex flex-col gap-2.5">
            <li
                v-for="entry in entries"
                :key="entry.id"
                data-enter
                class="text-ink flex items-center gap-3.5 rounded-[22px] bg-white p-3 pr-4 shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]"
            >
                <div
                    class="shrink-0 rounded-[16px] p-[2px]"
                    :style="{
                        background: look(entry).frame,
                        boxShadow: look(entry).shadow,
                    }"
                >
                    <div
                        class="flex size-14 items-center justify-center overflow-hidden rounded-[14px]"
                        :style="{ background: look(entry).panel }"
                    >
                        <FittedSprite
                            :src="`/storage/${entry.spritePath}`"
                            :size="56"
                            :padding="6"
                        />
                    </div>
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span
                        class="text-[15.5px] leading-tight font-black text-balance"
                    >
                        {{ entry.trainerNickname }} abriu um
                        {{ entry.speciesName }}
                    </span>
                    <span class="text-soft text-xs font-semibold">
                        {{ entry.caughtAgo }} ·
                        {{ formatMint(entry.mintNumber) }}
                    </span>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1.5">
                    <span
                        class="rounded-full px-2.5 py-1 text-[11px] font-black tracking-[0.06em] uppercase"
                        :style="{
                            background: look(entry).badge,
                            color: look(entry).badgeInk,
                        }"
                        >{{ look(entry).label }}</span
                    >
                    <TrainerAvatar
                        :sprite-path="entry.trainerAvatarSpritePath"
                        size="sm"
                        class="max-sm:hidden"
                    />
                </div>
            </li>
        </ol>
    </GroupScreen>
</template>
