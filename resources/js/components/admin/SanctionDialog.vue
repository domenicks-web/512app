<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ban, suspend } from '@/routes/admin/players';
import AdminDialog from './AdminDialog.vue';
import PlayerAvatar from './PlayerAvatar.vue';
import type { Player } from './types';

/**
 * Suspender (prazo em dias + motivo) ou banir (motivo + `Nick#1234`
 * digitado). O banimento é permanente e liberta os espécimes do jogador
 * pro Professor Carvalho; o diálogo diz quantos.
 */
const props = defineProps<{
    player: Player | null;
    mode: 'suspend' | 'ban';
    days: number[];
}>();
const emit = defineEmits<{ close: [] }>();

const chosenDays = ref<number | null>(null);
const reason = ref('');
const typed = ref('');
const error = ref<string | null>(null);
const busy = ref(false);

const name = computed(
    () => props.player?.nickname ?? props.player?.email ?? '',
);
const handle = computed(() =>
    props.player?.nickname && props.player.tag
        ? `${props.player.nickname}#${props.player.tag}`
        : '',
);
const canSubmit = computed(
    () =>
        !busy.value &&
        reason.value.trim().length >= 5 &&
        (props.mode === 'suspend'
            ? chosenDays.value !== null
            : typed.value.trim() === handle.value),
);

watch(
    () => [props.player, props.mode],
    () => {
        chosenDays.value = null;
        reason.value = '';
        typed.value = '';
        error.value = null;
    },
);

function submit() {
    if (!props.player || !canSubmit.value) {
        return;
    }

    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
        onSuccess: () => emit('close'),
        onError: (errors: Record<string, string>) =>
            (error.value =
                errors.sanction ??
                errors.reason ??
                errors.days ??
                errors.confirm ??
                'Não deu certo. Tente de novo.'),
    };

    if (props.mode === 'suspend') {
        router.post(
            suspend.url(props.player.id),
            { days: chosenDays.value, reason: reason.value.trim() },
            options,
        );
    } else {
        router.post(
            ban.url(props.player.id),
            { reason: reason.value.trim(), confirm: typed.value.trim() },
            options,
        );
    }
}
</script>

<template>
    <AdminDialog
        :open="player !== null"
        labelled-by="sanction-dialog-title"
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
                    id="sanction-dialog-title"
                    class="text-ink text-[22px] leading-tight font-black tracking-[-0.02em]"
                >
                    {{
                        mode === 'suspend'
                            ? `Suspender ${name}?`
                            : `Banir ${name}?`
                    }}
                </h2>
                <p
                    class="text-soft text-[14.5px] leading-relaxed font-semibold"
                >
                    <template v-if="mode === 'suspend'">
                        Sai de todos os aparelhos, as trocas abertas caem e some
                        do grupo até o fim do prazo. Os espécimes ficam com ele.
                        Recebe um email com o motivo.
                    </template>
                    <template v-else>
                        É permanente e não tem volta. Os
                        <strong class="text-ink"
                            >{{ player.specimens_count }} espécimes</strong
                        >
                        dele são libertados pro Professor Carvalho, as amizades
                        e as trocas abertas somem. Recebe um email com o motivo.
                    </template>
                </p>
            </div>
            <form
                class="flex w-full flex-col gap-3 text-left"
                @submit.prevent="submit"
            >
                <div v-if="mode === 'suspend'" class="flex flex-col gap-2">
                    <span class="text-muted text-[13px] font-extrabold"
                        >Por quanto tempo</span
                    >
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in days"
                            :key="option"
                            type="button"
                            class="h-10 cursor-pointer rounded-full px-4 text-sm font-extrabold"
                            :class="
                                chosenDays === option
                                    ? 'bg-ink text-white'
                                    : 'bg-field text-ink'
                            "
                            :aria-pressed="chosenDays === option"
                            @click="chosenDays = option"
                        >
                            {{ option }} {{ option === 1 ? 'dia' : 'dias' }}
                        </button>
                    </div>
                </div>
                <label class="flex flex-col gap-2">
                    <span class="text-muted text-[13px] font-extrabold"
                        >Motivo (vai no email)</span
                    >
                    <textarea
                        v-model="reason"
                        rows="3"
                        maxlength="500"
                        class="bg-field text-ink rounded-2xl px-4 py-3 text-[15px] font-semibold outline-none focus:bg-white focus:shadow-[inset_0_0_0_2px_#f24d4d]"
                    />
                </label>
                <label v-if="mode === 'ban'" class="flex flex-col gap-2">
                    <span class="text-muted text-[13px] font-extrabold"
                        >Digite <span class="text-ink">{{ handle }}</span> para
                        confirmar</span
                    >
                    <input
                        v-model="typed"
                        autocomplete="off"
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
                        {{ mode === 'suspend' ? 'Suspender' : 'Banir' }}
                    </button>
                </div>
            </form>
        </template>
    </AdminDialog>
</template>
