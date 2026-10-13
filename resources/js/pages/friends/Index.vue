<script setup lang="ts">
import { computed } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { formatFriendsOnline } from '@/lib/format';

defineOptions({ layout: EntryLayout });

/**
 * O grupo inteiro (o jogo é fechado por convite), online primeiro. Mostra
 * o `nickname#tag` completo, que é como um jogador acha o outro.
 */
type Player = {
    userId: number;
    nickname: string;
    tag: string;
    avatarSpritePath: string | null;
    ownedSpecies: number;
    isOnline: boolean;
    isYou: boolean;
};

const props = defineProps<{
    players: Player[];
}>();

const othersOnline = computed(
    () =>
        props.players.filter((player) => player.isOnline && !player.isYou)
            .length,
);
</script>

<template>
    <GroupScreen
        title="Amigos"
        :subtitle="`${players.length} no grupo · ${formatFriendsOnline(othersOnline)}`"
        active="friends"
    >
        <ul class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-3">
            <li
                v-for="player in players"
                :key="player.userId"
                data-enter
                class="flex items-center gap-3.5 rounded-[22px] bg-white p-3 pr-4 shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]"
            >
                <div class="relative">
                    <TrainerAvatar
                        :sprite-path="player.avatarSpritePath"
                        :highlighted="player.isYou"
                    />
                    <span
                        v-if="player.isOnline"
                        class="bg-online absolute -right-0.5 -bottom-0.5 size-3.5 rounded-full shadow-[0_0_0_2.5px_#fff]"
                        aria-hidden="true"
                    />
                </div>
                <div class="flex min-w-0 flex-col gap-px">
                    <span class="text-ink truncate text-[15.5px] font-black">
                        {{ player.nickname
                        }}<span class="text-faint-2">#{{ player.tag }}</span>
                    </span>
                    <span class="text-soft text-xs font-semibold">
                        {{ player.ownedSpecies }} espécies ·
                        <span :class="player.isOnline ? 'text-online' : ''">{{
                            player.isYou
                                ? 'você'
                                : player.isOnline
                                  ? 'online'
                                  : 'offline'
                        }}</span>
                    </span>
                </div>
            </li>
        </ul>
    </GroupScreen>
</template>
