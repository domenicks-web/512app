<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { role } from '@/routes/admin/players';
import AdminDialog from './AdminDialog.vue';
import PlayerAvatar from './PlayerAvatar.vue';
import type { Player } from './types';

/**
 * Confirmação de promover/rebaixar: digitar "Nick#1234" (o nickname sozinho
 * se repete). O servidor confere de novo; a lista atualiza otimista e
 * volta sozinha se der erro.
 */
const props = defineProps<{ player: Player | null }>();
const emit = defineEmits<{ close: [] }>();

const typed = ref('');
const error = ref<string | null>(null);
const busy = ref(false);

const promoting = computed(() => !props.player?.is_admin);
const name = computed(
    () => props.player?.nickname ?? props.player?.email ?? '',
);
const matches = computed(
    () =>
        !!props.player &&
        typed.value.trim().toLowerCase() === props.player.handle.toLowerCase(),
);

watch(
    () => props.player,
    () => {
        typed.value = '';
        error.value = null;
    },
);

function submit() {
    const player = props.player;

    if (!player || !matches.value || busy.value) {
        return;
    }

    const isAdmin = promoting.value;

    router
        .optimistic((pageProps: { players?: unknown }) => ({
            players: (pageProps.players as Player[]).map((item) =>
                item.id === player.id ? { ...item, is_admin: isAdmin } : item,
            ),
        }))
        .patch(
            role.url(player.id),
            { is_admin: isAdmin, confirm: typed.value },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => (busy.value = true),
                onFinish: () => (busy.value = false),
                onSuccess: () => emit('close'),
                onError: (errors) =>
                    (error.value =
                        errors.confirm ??
                        errors.role ??
                        'Não deu pra mudar o papel.'),
            },
        );
}
</script>

<template>
    <AdminDialog
        :open="player !== null"
        labelled-by="role-dialog-title"
        :busy="busy"
        @close="emit('close')"
    >
        <template v-if="player">
            <PlayerAvatar
                :sprite-path="player.avatar_sprite_path"
                :name="name"
                :size="72"
                is-admin
                :badge-color="promoting ? '#f24d4d' : '#a3a8b0'"
            />
            <div class="flex flex-col gap-2">
                <h2
                    id="role-dialog-title"
                    class="text-ink text-[22px] leading-tight font-black tracking-[-0.02em]"
                >
                    {{
                        promoting
                            ? `Tornar ${name} admin?`
                            : `Remover admin de ${name}?`
                    }}
                </h2>
                <p
                    class="text-soft text-[14.5px] leading-relaxed font-semibold"
                >
                    {{
                        promoting
                            ? 'Vai poder gerar convites sem cota, revogar códigos e promover ou rebaixar outros jogadores.'
                            : 'Volta a ser jogador comum, com cota de convites normal. A coleção não muda.'
                    }}
                </p>
            </div>
            <form class="flex w-full flex-col gap-2" @submit.prevent="submit">
                <label
                    for="role-confirm"
                    class="text-muted text-[13px] font-bold"
                >
                    Digite
                    <span class="text-ink font-mono font-extrabold">{{
                        player.handle
                    }}</span>
                    para confirmar
                </label>
                <input
                    id="role-confirm"
                    v-model="typed"
                    type="text"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    class="bg-field text-ink h-[50px] rounded-full px-5 text-center font-mono text-[16px] font-extrabold outline-none focus:bg-white focus:shadow-[inset_0_0_0_2px_#f24d4d]"
                />
                <p
                    v-if="error"
                    class="text-brand-ink text-[13px] font-bold"
                    role="alert"
                >
                    {{ error }}
                </p>
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="text-ink h-[52px] cursor-pointer rounded-full bg-white text-[15px] font-extrabold shadow-[inset_0_0_0_2px_#e4e6ea]"
                        @click="emit('close')"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="h-[52px] rounded-full text-[15px] font-extrabold text-white transition-colors"
                        :class="
                            matches && !busy
                                ? 'bg-brand cursor-pointer shadow-[0_10px_22px_rgba(242,77,77,0.42)]'
                                : 'cursor-not-allowed bg-[#f5b3b3]'
                        "
                        :disabled="!matches || busy"
                    >
                        {{ promoting ? 'Tornar admin' : 'Remover' }}
                    </button>
                </div>
            </form>
        </template>
    </AdminDialog>
</template>
