<script setup lang="ts">
import { formatFriendsOnline } from '@/lib/format';

/** Bolinha (verde e pulsando quando tem alguém) + "N amigos online". */
defineProps<{ count: number }>();
</script>

<template>
    <div class="flex items-center gap-[9px]">
        <div class="relative size-[9px]" aria-hidden="true">
            <div
                v-if="count > 0"
                class="bg-online animate-online-pulse absolute inset-0 rounded-full"
            />
            <div
                class="absolute inset-0 rounded-full"
                :class="count > 0 ? 'bg-online' : 'bg-faint-2'"
            />
        </div>
        <div class="text-muted text-[12.5px] font-extrabold whitespace-nowrap">
            {{ formatFriendsOnline(count) }}
        </div>
    </div>
</template>
