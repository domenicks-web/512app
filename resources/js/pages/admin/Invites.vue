<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminIcon from '@/components/admin/AdminIcon.vue';
import AdminShell from '@/components/admin/AdminShell.vue';
import { copyText } from '@/components/admin/clipboard';
import FilterChips from '@/components/admin/FilterChips.vue';
import InviteGenerator from '@/components/admin/InviteGenerator.vue';
import type { InviteForm } from '@/components/admin/types';
import PlayerAvatar from '@/components/admin/PlayerAvatar.vue';
import { showToast } from '@/components/admin/toast';
import PokeballMark from '@/components/entry/PokeballMark.vue';
import { formatDayMonth } from '@/lib/format';
import { revoke } from '@/routes/admin/invites';

type InviteStatus = 'active' | 'exhausted' | 'expired' | 'revoked';

type Invite = {
    id: number;
    code: string;
    status: InviteStatus;
    max_uses: number;
    uses_count: number;
    expires_at: string | null;
    created_at: string;
    created_by: string;
    redemptions: {
        nickname: string;
        handle: string;
        avatar_sprite_path: string | null;
    }[];
};

const props = defineProps<{
    invites: Invite[];
    counts: { invites: number; players: number };
    form: InviteForm;
}>();

const page = usePage();

const statusMeta: Record<
    InviteStatus,
    { label: string; plural: string; pill: string }
> = {
    active: {
        label: 'Ativo',
        plural: 'Ativos',
        pill: 'bg-[#e3f1ea] text-[#2f7556]',
    },
    exhausted: {
        label: 'Esgotado',
        plural: 'Esgotados',
        pill: 'bg-[#eceef1] text-[#656b74]',
    },
    expired: {
        label: 'Expirado',
        plural: 'Expirados',
        pill: 'bg-[#f6e9df] text-[#8f5735]',
    },
    revoked: {
        label: 'Revogado',
        plural: 'Revogados',
        pill: 'bg-[#ffe6e6] text-[#d63c3c]',
    },
};

const filter = ref<'all' | InviteStatus>('all');
const isGeneratorOpen = ref(false);
const copiedId = ref<number | null>(null);
const freshId = computed(() => page.flash.createdInviteId ?? null);

const filterOptions = computed(() => [
    { value: 'all' as const, label: 'Todos', count: props.invites.length },
    ...(Object.keys(statusMeta) as InviteStatus[]).map((status) => ({
        value: status,
        label: statusMeta[status].plural,
        count: props.invites.filter((invite) => invite.status === status)
            .length,
    })),
]);

const visibleInvites = computed(() =>
    filter.value === 'all'
        ? props.invites
        : props.invites.filter((invite) => invite.status === filter.value),
);

function onCreated() {
    isGeneratorOpen.value = false;
    filter.value = 'all';
}

async function copy(invite: Invite) {
    if (await copyText(invite.code)) {
        copiedId.value = invite.id;
        showToast(`${invite.code} copiado`);
        setTimeout(() => {
            if (copiedId.value === invite.id) {
                copiedId.value = null;
            }
        }, 1400);
    }
}

function revokeInvite(invite: Invite) {
    router.post(
        revoke.url(invite.id),
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
    <AdminShell title="Convites" :counts="counts">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1.5">
                <h1
                    class="text-[34px] leading-[1.05] font-black tracking-[-0.03em]"
                >
                    Convites
                </h1>
                <p class="text-sm font-semibold text-[#6c737c]">
                    Cada código libera um cadastro. Revogar não afeta quem já
                    entrou.
                </p>
            </div>
            <button
                type="button"
                class="bg-brand flex h-[50px] cursor-pointer items-center gap-2 rounded-full pr-[22px] pl-[18px] text-[15px] font-extrabold text-white shadow-[0_10px_22px_rgba(242,77,77,0.42)] transition-transform active:scale-[0.97]"
                :aria-expanded="isGeneratorOpen"
                @click="isGeneratorOpen = !isGeneratorOpen"
            >
                <PokeballMark />
                {{ isGeneratorOpen ? 'Fechar' : 'Gerar convite' }}
            </button>
        </div>

        <InviteGenerator
            v-if="isGeneratorOpen"
            :form="form"
            @created="onCreated"
        />

        <FilterChips v-model="filter" :options="filterOptions" />

        <div
            class="overflow-x-auto rounded-3xl bg-white shadow-[0_2px_3px_rgba(28,32,38,0.04),0_12px_32px_rgba(28,32,38,0.07)]"
        >
            <div class="min-w-[750px]" role="table" aria-label="Convites">
                <div
                    role="row"
                    class="invite-grid border-line border-b px-6 py-4 text-[11px] font-extrabold tracking-[0.12em] text-[#8a9099] uppercase"
                >
                    <span role="columnheader">Código</span>
                    <span role="columnheader">Usado por</span>
                    <span role="columnheader">Usos</span>
                    <span role="columnheader">Expira</span>
                    <span role="columnheader">Status</span>
                    <span role="columnheader" class="sr-only">Ações</span>
                </div>

                <div
                    v-for="invite in visibleInvites"
                    :key="invite.id"
                    role="row"
                    class="invite-grid border-line items-center border-b px-6 py-4 transition-colors last:border-b-0 hover:bg-[#fafafb]"
                    :class="[
                        invite.status === 'expired' ||
                        invite.status === 'revoked'
                            ? 'opacity-60'
                            : '',
                        invite.id === freshId ? 'row-in' : '',
                    ]"
                >
                    <div role="cell" class="flex min-w-0 flex-col gap-0.5">
                        <span
                            class="font-mono text-[15px] font-extrabold"
                            :class="
                                invite.status === 'revoked'
                                    ? 'line-through'
                                    : ''
                            "
                            >{{ invite.code }}</span
                        >
                        <span class="text-xs font-semibold text-[#8a9099]"
                            >por {{ invite.created_by }} ·
                            {{ formatDayMonth(invite.created_at) }}</span
                        >
                    </div>

                    <div role="cell" class="flex min-w-0 items-center gap-2">
                        <template v-if="invite.redemptions.length">
                            <div class="flex shrink-0">
                                <PlayerAvatar
                                    v-for="(
                                        redemption, index
                                    ) in invite.redemptions.slice(0, 3)"
                                    :key="redemption.handle"
                                    :sprite-path="redemption.avatar_sprite_path"
                                    :name="redemption.nickname"
                                    :size="28"
                                    ring
                                    :class="index > 0 ? '-ml-1.5' : ''"
                                />
                            </div>
                            <span class="truncate text-sm font-extrabold">{{
                                invite.redemptions.length === 1
                                    ? invite.redemptions[0].handle
                                    : invite.redemptions
                                          .map(
                                              (redemption) =>
                                                  redemption.nickname,
                                          )
                                          .join(', ')
                            }}</span>
                        </template>
                        <span v-else class="text-faint-2 text-sm font-semibold"
                            >Ninguém ainda</span
                        >
                    </div>

                    <div role="cell" class="flex flex-col gap-1.5">
                        <span class="text-[13px] font-extrabold"
                            >{{ invite.uses_count }} de
                            {{ invite.max_uses }}</span
                        >
                        <span
                            class="block h-1.5 w-[84px] overflow-hidden rounded-full bg-[#eef0f2]"
                        >
                            <span
                                class="block h-full rounded-full"
                                :class="
                                    invite.status === 'active'
                                        ? 'bg-brand'
                                        : 'bg-[#c3c7cd]'
                                "
                                :style="{
                                    width: `${Math.min(100, (invite.uses_count / invite.max_uses) * 100)}%`,
                                }"
                            />
                        </span>
                    </div>

                    <span
                        role="cell"
                        class="text-sm font-semibold text-[#565d66]"
                        >{{
                            invite.expires_at
                                ? formatDayMonth(invite.expires_at)
                                : 'Nunca'
                        }}</span
                    >

                    <span role="cell">
                        <span
                            class="inline-flex h-[26px] items-center gap-1.5 rounded-full px-2.5 text-[12.5px] font-extrabold"
                            :class="statusMeta[invite.status].pill"
                        >
                            <span class="size-1.5 rounded-full bg-current" />
                            {{ statusMeta[invite.status].label }}
                        </span>
                    </span>

                    <div role="cell" class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="flex size-9 cursor-pointer items-center justify-center rounded-full transition-colors"
                            :class="
                                copiedId === invite.id
                                    ? 'bg-[#e3f1ea] text-[#2f7556]'
                                    : 'bg-field text-ink hover:bg-[#eceef0]'
                            "
                            :aria-label="`Copiar ${invite.code}`"
                            @click="copy(invite)"
                        >
                            <AdminIcon
                                :name="
                                    copiedId === invite.id ? 'check' : 'copy'
                                "
                            />
                        </button>
                        <button
                            v-if="invite.status === 'active'"
                            type="button"
                            class="bg-field text-soft hover:text-brand flex size-9 cursor-pointer items-center justify-center rounded-full transition-colors hover:bg-[#ffe6e6]"
                            :aria-label="`Revogar ${invite.code}`"
                            @click="revokeInvite(invite)"
                        >
                            <AdminIcon name="revoke" />
                        </button>
                    </div>
                </div>

                <p
                    v-if="!visibleInvites.length"
                    class="text-faint-2 px-6 py-10 text-center text-sm font-semibold"
                >
                    Nenhum convite nesse filtro.
                </p>
            </div>
        </div>
    </AdminShell>
</template>

<style scoped>
.invite-grid {
    display: grid;
    grid-template-columns:
        minmax(150px, 1.4fr) minmax(120px, 1.2fr)
        96px 84px 100px 80px;
    gap: 14px;
}

.row-in {
    animation: row-in 0.45s cubic-bezier(0.2, 1.4, 0.4, 1);
}

@keyframes row-in {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .row-in {
        animation: none;
    }
}
</style>
