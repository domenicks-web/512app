<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { quota as quotaRoute } from '@/routes/admin/players';
import { showToast } from './toast';
import type { Player } from './types';

/** Cota de convites (−/+). Atualiza otimista e volta sozinha se o servidor recusar. */
const props = defineProps<{
    player: Player;
    range: { min: number; max: number };
}>();

function setQuota(value: number) {
    if (value < props.range.min || value > props.range.max) {
        return;
    }

    router
        .optimistic((pageProps: { players?: unknown }) => ({
            players: (pageProps.players as Player[]).map((item) =>
                item.id === props.player.id
                    ? { ...item, invite_quota: value }
                    : item,
            ),
        }))
        .patch(
            quotaRoute.url(props.player.id),
            { quota: value },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) =>
                    showToast(errors.quota ?? 'Não deu pra mudar a cota.'),
            },
        );
}
</script>

<template>
    <span
        v-if="player.is_admin"
        class="bg-field text-soft inline-flex h-9 items-center rounded-full px-4 text-[13px] font-extrabold whitespace-nowrap"
        >Sem limite</span
    >
    <div
        v-else
        class="bg-field inline-flex h-9 items-center gap-0.5 rounded-full p-[3px]"
        role="group"
        :aria-label="`Cota de convites de ${player.handle}`"
    >
        <button
            type="button"
            class="flex size-[30px] cursor-pointer items-center justify-center rounded-full bg-white font-black shadow-[0_1px_3px_rgba(28,32,38,0.1)] disabled:cursor-default disabled:opacity-40"
            :disabled="player.invite_quota <= range.min"
            aria-label="Menos convites"
            @click="setQuota(player.invite_quota - 1)"
        >
            −
        </button>
        <span class="w-7 text-center text-sm font-black" aria-live="polite">{{
            player.invite_quota
        }}</span>
        <button
            type="button"
            class="flex size-[30px] cursor-pointer items-center justify-center rounded-full bg-white font-black shadow-[0_1px_3px_rgba(28,32,38,0.1)] disabled:cursor-default disabled:opacity-40"
            :disabled="player.invite_quota >= range.max"
            aria-label="Mais convites"
            @click="setQuota(player.invite_quota + 1)"
        >
            +
        </button>
    </div>
</template>
