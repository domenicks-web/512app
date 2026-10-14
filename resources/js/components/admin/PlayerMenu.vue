<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { birthdate, pack, verification } from '@/routes/admin/players';
import AdminIcon from './AdminIcon.vue';
import { showToast } from './toast';
import type { Player } from './types';

/**
 * Menu "⋯" da linha do jogador: ações que o handoff não desenhou (liberar
 * correção da data, reenviar confirmação, trocar senha).
 */
const props = defineProps<{ player: Player; isSelf: boolean }>();
const emit = defineEmits<{ changePassword: [player: Player] }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const position = ref({ top: 0, right: 0 });

// O menu vai pro <body> (a tabela rola na horizontal e cortaria ele), preso
// embaixo do botão. Rolar ou redimensionar fecha.
const isMounted = ref(false);

function toggle() {
    if (!open.value && trigger.value) {
        const rect = trigger.value.getBoundingClientRect();
        const right = window.innerWidth - rect.right;
        // Sem espaço embaixo (último da lista no celular): abre pra cima.
        position.value =
            window.innerHeight - rect.bottom < 300
                ? { top: Math.max(8, rect.top - 8 - 290), right }
                : { top: rect.bottom + 8, right };
    }

    open.value = !open.value;
}

function close() {
    open.value = false;
}

function closeOnOutside(event: MouseEvent) {
    const target = event.target as Node;

    if (!root.value?.contains(target) && !panel.value?.contains(target)) {
        open.value = false;
    }
}

function closeOnEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    isMounted.value = true;
    document.addEventListener('click', closeOnOutside);
    document.addEventListener('keydown', closeOnEscape);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutside);
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
});

function post(url: string, errorKey: string) {
    open.value = false;
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) =>
                showToast(errors[errorKey] ?? 'Não deu certo. Tente de novo.'),
        },
    );
}

/**
 * Item desabilitado mostra o motivo embaixo (no celular não tem tooltip).
 */
const items = computed(() => [
    {
        label: 'Liberar correção da data',
        disabledReason: props.player.is_birthdate_locked
            ? null
            : 'Ainda não usou a correção que tem',
        danger: false,
        run: () => post(birthdate.url(props.player.id), 'birthdate'),
    },
    {
        label: 'Reenviar email de confirmação',
        disabledReason: props.player.is_email_verified
            ? 'Email já confirmado'
            : null,
        danger: false,
        run: () => post(verification.url(props.player.id), 'verification'),
    },
    {
        label: 'Liberar próximo pack',
        disabledReason: props.player.has_pack_available
            ? 'O pack já está disponível'
            : null,
        danger: false,
        run: () => post(pack.url(props.player.id), 'pack'),
    },
    {
        label: 'Trocar senha',
        disabledReason: props.isSelf ? 'A sua se troca no editar perfil' : null,
        danger: true,
        run: changePassword,
    },
]);

function changePassword() {
    open.value = false;
    emit('changePassword', props.player);
}
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="bg-field text-ink flex size-9 cursor-pointer items-center justify-center rounded-full transition-colors hover:bg-[#eceef0]"
            :aria-expanded="open"
            :aria-label="`Mais ações de ${player.handle}`"
            ref="trigger"
            @click="toggle"
        >
            <AdminIcon name="more" />
        </button>
        <Teleport v-if="isMounted" to="body">
            <div
                v-if="open"
                ref="panel"
                role="menu"
                :style="{
                    top: `${position.top}px`,
                    right: `${position.right}px`,
                }"
                class="entry-light text-ink fixed z-40 flex w-max flex-col rounded-2xl bg-white p-1.5 text-left font-sans whitespace-nowrap shadow-[0_2px_4px_rgba(28,32,38,0.06),0_16px_40px_rgba(28,32,38,0.16)]"
            >
                <button
                    v-for="item in items"
                    :key="item.label"
                    type="button"
                    role="menuitem"
                    class="hover:bg-field flex cursor-pointer flex-col gap-0.5 rounded-xl px-3 py-2.5 text-left disabled:cursor-default disabled:hover:bg-transparent"
                    :disabled="item.disabledReason !== null"
                    @click="item.run"
                >
                    <span
                        class="text-sm font-extrabold"
                        :class="[
                            item.danger ? 'text-brand-ink' : 'text-ink',
                            item.disabledReason ? 'opacity-40' : '',
                        ]"
                        >{{ item.label }}</span
                    >
                    <span
                        v-if="item.disabledReason"
                        class="text-faint-2 text-xs font-semibold"
                        >{{ item.disabledReason }}</span
                    >
                </button>
            </div>
        </Teleport>
    </div>
</template>
