<script setup lang="ts">
import PlayerAvatar from './PlayerAvatar.vue';
import type { Invite } from './types';

/** "Usado por": avatares sobrepostos + Nick#1234 (um uso) ou os nicks. */
defineProps<{ invite: Invite }>();
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <template v-if="invite.redemptions.length">
            <div class="flex shrink-0">
                <PlayerAvatar
                    v-for="(redemption, index) in invite.redemptions.slice(
                        0,
                        3,
                    )"
                    :key="redemption.handle"
                    :sprite-path="redemption.avatar_sprite_path"
                    :name="redemption.nickname"
                    :size="28"
                    ring
                    :class="index > 0 ? '-ml-1.5' : ''"
                />
            </div>
            <span class="truncate text-sm font-extrabold">{{
                invite.redemptions.length === 1
                    ? invite.redemptions[0].handle
                    : invite.redemptions
                          .map((redemption) => redemption.nickname)
                          .join(', ')
            }}</span>
        </template>
        <span v-else class="text-faint-2 text-sm font-semibold"
            >Ninguém ainda</span
        >
    </div>
</template>
