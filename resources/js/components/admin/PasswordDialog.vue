<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { password as passwordRoute } from '@/routes/admin/players';
import AdminDialog from './AdminDialog.vue';
import PlayerAvatar from './PlayerAvatar.vue';
import type { Player } from './types';

/** Admin define a senha nova de outro jogador; ele cai de todos os aparelhos e recebe email. */
const props = defineProps<{ player: Player | null }>();
const emit = defineEmits<{ close: [] }>();

const password = ref('');
const confirmation = ref('');
const error = ref<string | null>(null);
const busy = ref(false);

const name = computed(
    () => props.player?.nickname ?? props.player?.email ?? '',
);
const canSubmit = computed(
    () =>
        password.value.length > 0 &&
        confirmation.value.length > 0 &&
        !busy.value,
);

watch(
    () => props.player,
    () => {
        password.value = '';
        confirmation.value = '';
        error.value = null;
    },
);

function submit() {
    if (!props.player || !canSubmit.value) {
        return;
    }

    router.put(
        passwordRoute.url(props.player.id),
        { password: password.value, password_confirmation: confirmation.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
            onSuccess: () => emit('close'),
            onError: (errors) =>
                (error.value =
                    errors.password ?? 'Não deu pra trocar a senha.'),
        },
    );
}
</script>

<template>
    <AdminDialog
        :open="player !== null"
        labelled-by="password-dialog-title"
        :busy="busy"
        @close="emit('close')"
    >
        <template v-if="player">
            <PlayerAvatar
                :sprite-path="player.avatar_sprite_path"
                :name="name"
                :size="72"
            />
            <div class="flex flex-col gap-2">
                <h2
                    id="password-dialog-title"
                    class="text-ink text-[22px] leading-tight font-black tracking-[-0.02em]"
                >
                    Trocar a senha de {{ name }}?
                </h2>
                <p
                    class="text-soft text-[14.5px] leading-relaxed font-semibold"
                >
                    A conta sai de todos os aparelhos e recebe um email
                    avisando. Passe a senha nova por fora.
                </p>
            </div>
            <form
                class="flex w-full flex-col gap-3 text-left"
                @submit.prevent="submit"
            >
                <label class="flex flex-col gap-2">
                    <span class="text-muted text-[13px] font-extrabold"
                        >Senha nova</span
                    >
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        class="bg-field text-ink h-12 rounded-full px-5 text-[15px] font-semibold outline-none focus:bg-white focus:shadow-[inset_0_0_0_2px_#f24d4d]"
                    />
                </label>
                <label class="flex flex-col gap-2">
                    <span class="text-muted text-[13px] font-extrabold"
                        >Repita a senha nova</span
                    >
                    <input
                        v-model="confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="bg-field text-ink h-12 rounded-full px-5 text-[15px] font-semibold outline-none focus:bg-white focus:shadow-[inset_0_0_0_2px_#f24d4d]"
                    />
                </label>
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
                        class="h-[52px] rounded-full text-[15px] font-extrabold text-white"
                        :class="
                            canSubmit
                                ? 'bg-brand cursor-pointer shadow-[0_10px_22px_rgba(242,77,77,0.42)]'
                                : 'cursor-not-allowed bg-[#f5b3b3]'
                        "
                        :disabled="!canSubmit"
                    >
                        Trocar senha
                    </button>
                </div>
            </form>
        </template>
    </AdminDialog>
</template>
