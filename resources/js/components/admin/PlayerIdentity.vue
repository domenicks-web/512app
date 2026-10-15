<script setup lang="ts">
import { formatDayMonth } from '@/lib/format';
import PlayerAvatar from './PlayerAvatar.vue';
import type { Player } from './types';

/** Avatar + Nick #tag (+ "você") + email, e a pílula da sanção ativa. */
defineProps<{ player: Player; isSelf: boolean }>();
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <PlayerAvatar
            :sprite-path="player.avatar_sprite_path"
            :name="player.nickname ?? player.email"
            :is-admin="player.is_admin"
        />
        <div class="flex min-w-0 flex-col">
            <div class="flex items-baseline gap-1">
                <span class="text-[15px] font-black whitespace-nowrap">{{
                    player.nickname ?? 'Sem perfil'
                }}</span>
                <span
                    v-if="player.tag"
                    class="text-faint-2 text-[13px] font-semibold"
                    >#{{ player.tag }}</span
                >
                <span
                    v-if="isSelf"
                    class="bg-base ml-1 rounded-full px-2 py-0.5 text-[11px] font-extrabold"
                    >você</span
                >
            </div>
            <span
                v-if="player.sanction"
                class="w-fit rounded-full px-2 py-0.5 text-[11px] font-extrabold"
                :class="
                    player.sanction.type === 'ban'
                        ? 'bg-[#2b2f36] text-white'
                        : 'bg-[#ffe6e6] text-[#d63c3c]'
                "
                :title="player.sanction.reason"
                >{{
                    player.sanction.type === 'ban'
                        ? 'Banido'
                        : `Suspenso até ${formatDayMonth(player.sanction.endsAt ?? '')}`
                }}</span
            >
            <span class="truncate text-xs font-semibold text-[#8a9099]">{{
                player.email
            }}</span>
        </div>
    </div>
</template>
