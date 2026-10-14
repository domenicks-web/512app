<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { revoke } from '@/routes/admin/invites';
import AdminIcon from './AdminIcon.vue';
import { copyText } from './clipboard';
import { showToast } from './toast';
import type { Invite } from './types';

/** Copiar (vira check verde por 1,4s) e revogar (só ativo, sem modal). */
const props = defineProps<{ invite: Invite }>();

const copied = ref(false);

async function copy() {
    if (await copyText(props.invite.code)) {
        copied.value = true;
        showToast(`${props.invite.code} copiado`);
        setTimeout(() => (copied.value = false), 1400);
    }
}

function revokeInvite() {
    router.post(
        revoke.url(props.invite.id),
        {},
        {
            preserveScroll: true,
            onError: (errors) =>
                showToast(errors.invite ?? 'Não deu pra revogar.'),
        },
    );
}
</script>

<template>
    <div class="flex justify-end gap-2">
        <button
            type="button"
            class="flex size-9 cursor-pointer items-center justify-center rounded-full transition-colors"
            :class="
                copied
                    ? 'bg-[#e3f1ea] text-[#2f7556]'
                    : 'bg-field text-ink hover:bg-[#eceef0]'
            "
            :aria-label="`Copiar ${invite.code}`"
            @click="copy"
        >
            <AdminIcon :name="copied ? 'check' : 'copy'" />
        </button>
        <button
            v-if="invite.status === 'active'"
            type="button"
            class="bg-field text-soft hover:text-brand flex size-9 cursor-pointer items-center justify-center rounded-full transition-colors hover:bg-[#ffe6e6]"
            :aria-label="`Revogar ${invite.code}`"
            @click="revokeInvite"
        >
            <AdminIcon name="revoke" />
        </button>
    </div>
</template>
