<script setup lang="ts">
import PlayerAvatar from './PlayerAvatar.vue';
import type { Player } from './types';

/** Avatar + Nick #tag (+ "você") + email. */
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
            <span class="truncate text-xs font-semibold text-[#8a9099]">{{
                player.email
            }}</span>
        </div>
    </div>
</template>
