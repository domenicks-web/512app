<script setup lang="ts">
import { inviteStatusMeta } from './types';
import type { InviteStatus } from './types';

defineProps<{ status: InviteStatus }>();
</script>

<template>
    <span
        class="inline-flex h-[26px] items-center gap-1.5 rounded-full px-2.5 text-[12.5px] font-extrabold whitespace-nowrap"
        :class="inviteStatusMeta[status].pill"
    >
        <span class="size-1.5 rounded-full bg-current" />
        {{ inviteStatusMeta[status].label }}
    </span>
</template>
