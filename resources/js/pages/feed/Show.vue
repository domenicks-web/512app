<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import FittedSprite from '@/components/FittedSprite.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { formatMint } from '@/lib/format';
import { rarityLook } from '@/lib/pack/rarity';
import type { Tier } from '@/lib/pack/types';
import { show as trainerShow } from '@/routes/trainer';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Feed do grupo: só o que é importante. As aberturas de shiny e lendário,
 * de quem abriu, e as trocas que movem um deles, com a moldura da raridade
 * no sprite. Os tiers ficam em `game.group.feed_highlight_tiers`.
 */
type FeedBase = {
    key: string;
    isShiny: boolean;
    trainerId: number;
    trainerNickname: string;
    trainerAvatarSpritePath: string | null;
    rarityTier: string;
    spritePath: string;
    caughtAgo: string;
};

type FeedEntry =
    | (FeedBase & {
          kind: 'opening';
          id: number;
          speciesName: string;
          /** Apelido que o dono deu depois. */
          nickname: string | null;
          mintNumber: number;
      })
    | (FeedBase & {
          kind: 'trade';
          tradeId: number;
          partnerNickname: string;
          summary: string;
      });

defineProps<{
    entries: FeedEntry[];
}>();

const look = (entry: FeedEntry) =>
    rarityLook(entry.rarityTier as Tier, entry.isShiny);
</script>

<template>
    <GroupScreen title="Feed" subtitle="Shinies, lendários e trocas do grupo">
        <p
            v-if="entries.length === 0"
            data-enter
            class="text-soft rounded-[22px] bg-white p-6 text-center text-sm font-semibold shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]"
        >
            Nada por aqui ainda. Quando alguém abrir um shiny ou um lendário,
            aparece aqui.
        </p>

        <ol v-else class="flex flex-col gap-2.5">
            <li v-for="entry in entries" :key="entry.key" data-enter>
                <Link
                    :href="trainerShow(entry.trainerId)"
                    class="text-ink flex items-center gap-3.5 rounded-[22px] bg-white p-3 pr-4 shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)] transition-transform active:scale-[0.98]"
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
                            <template v-if="entry.kind === 'trade'">
                                {{ entry.trainerNickname }} e
                                {{ entry.partnerNickname }} trocaram
                            </template>
                            <template v-else>
                                {{ entry.trainerNickname }} abriu um
                                {{ entry.speciesName }}
                            </template>
                        </span>
                        <span class="text-soft text-xs font-semibold">
                            {{ entry.caughtAgo }} ·
                            {{
                                entry.kind === 'trade'
                                    ? entry.summary
                                    : formatMint(entry.mintNumber)
                            }}<template
                                v-if="
                                    entry.kind === 'opening' && entry.nickname
                                "
                            >
                                · “{{ entry.nickname }}”</template
                            >
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
                </Link>
            </li>
        </ol>
    </GroupScreen>
</template>
