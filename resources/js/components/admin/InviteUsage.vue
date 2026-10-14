<script setup lang="ts">
import type { Invite } from './types';

defineProps<{ invite: Invite }>();
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-[13px] font-extrabold"
            >{{ invite.uses_count }} de {{ invite.max_uses }}</span
        >
        <span
            class="block h-1.5 w-[84px] overflow-hidden rounded-full bg-[#eef0f2]"
        >
            <span
                class="block h-full rounded-full"
                :class="
                    invite.status === 'active' ? 'bg-brand' : 'bg-[#c3c7cd]'
                "
                :style="{
                    width: `${Math.min(100, (invite.uses_count / invite.max_uses) * 100)}%`,
                }"
            />
        </span>
    </div>
</template>
