<script setup lang="ts">
import type { Player } from './types';

/** Botão do papel: travado em si mesmo e no último admin; o resto abre o RoleDialog. */
defineProps<{ player: Player; isSelf: boolean; isLastAdmin: boolean }>();
const emit = defineEmits<{ open: [player: Player] }>();
</script>

<template>
    <button
        v-if="isSelf || isLastAdmin"
        type="button"
        disabled
        class="text-ink h-9 rounded-full bg-white px-4 text-[13px] font-extrabold whitespace-nowrap opacity-50 shadow-[inset_0_0_0_2px_#e4e6ea]"
        :title="
            isSelf
                ? 'Você não pode remover seu próprio acesso'
                : 'O jogo precisa de pelo menos um admin'
        "
    >
        Admin
    </button>
    <button
        v-else-if="player.is_admin"
        type="button"
        class="text-ink h-9 cursor-pointer rounded-full bg-white px-4 text-[13px] font-extrabold whitespace-nowrap shadow-[inset_0_0_0_2px_#e4e6ea] hover:bg-[#fafafb]"
        @click="emit('open', player)"
    >
        Remover admin
    </button>
    <button
        v-else
        type="button"
        class="bg-ink h-9 cursor-pointer rounded-full px-4 text-[13px] font-extrabold whitespace-nowrap text-white"
        @click="emit('open', player)"
    >
        Tornar admin
    </button>
</template>
