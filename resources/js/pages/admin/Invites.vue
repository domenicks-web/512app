<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminShell from '@/components/admin/AdminShell.vue';
import FilterChips from '@/components/admin/FilterChips.vue';
import InviteActions from '@/components/admin/InviteActions.vue';
import InviteGenerator from '@/components/admin/InviteGenerator.vue';
import InviteRedeemers from '@/components/admin/InviteRedeemers.vue';
import InviteStatusPill from '@/components/admin/InviteStatusPill.vue';
import InviteUsage from '@/components/admin/InviteUsage.vue';
import { inviteStatusMeta } from '@/components/admin/types';
import type {
    Invite,
    InviteForm,
    InviteStatus,
} from '@/components/admin/types';
import PokeballMark from '@/components/entry/PokeballMark.vue';
import { formatDayMonth } from '@/lib/format';

/**
 * Convites: tabela do handoff a partir de 1024px; abaixo disso cada convite
 * vira um cartão (nada de rolagem horizontal no celular).
 */
const props = defineProps<{
    invites: Invite[];
    counts: { invites: number; players: number };
    form: InviteForm;
}>();

const page = usePage();

const filter = ref<'all' | InviteStatus>('all');
const isGeneratorOpen = ref(false);
const freshId = computed(() => page.flash.createdInviteId ?? null);

const filterOptions = computed(() => [
    { value: 'all' as const, label: 'Todos', count: props.invites.length },
    ...(Object.keys(inviteStatusMeta) as InviteStatus[]).map((status) => ({
        value: status,
        label: inviteStatusMeta[status].plural,
        count: props.invites.filter((invite) => invite.status === status)
            .length,
    })),
]);

const visibleInvites = computed(() =>
    filter.value === 'all'
        ? props.invites
        : props.invites.filter((invite) => invite.status === filter.value),
);

function isFaded(invite: Invite): boolean {
    return invite.status === 'expired' || invite.status === 'revoked';
}

function onCreated() {
    isGeneratorOpen.value = false;
    filter.value = 'all';
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
                class="bg-brand flex h-[50px] w-full cursor-pointer items-center justify-center gap-2 rounded-full pr-[22px] pl-[18px] text-[15px] font-extrabold text-white shadow-[0_10px_22px_rgba(242,77,77,0.42)] transition-transform active:scale-[0.97] sm:w-auto"
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
            class="hidden rounded-3xl bg-white shadow-[0_2px_3px_rgba(28,32,38,0.04),0_12px_32px_rgba(28,32,38,0.07)] lg:block"
            role="table"
            aria-label="Convites"
        >
            <div
                role="row"
                class="border-line grid grid-cols-[minmax(150px,1.4fr)_minmax(120px,1.2fr)_96px_84px_100px_80px] gap-[14px] border-b px-6 py-4 text-[11px] font-extrabold tracking-[0.12em] text-[#8a9099] uppercase"
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
                class="border-line grid grid-cols-[minmax(150px,1.4fr)_minmax(120px,1.2fr)_96px_84px_100px_80px] items-center gap-[14px] border-b px-6 py-4 transition-colors last:border-b-0 hover:bg-[#fafafb]"
                :class="[
                    isFaded(invite) ? 'opacity-60' : '',
                    invite.id === freshId ? 'row-in' : '',
                ]"
            >
                <div role="cell" class="flex min-w-0 flex-col gap-0.5">
                    <span
                        class="font-mono text-[15px] font-extrabold"
                        :class="
                            invite.status === 'revoked' ? 'line-through' : ''
                        "
                        >{{ invite.code }}</span
                    >
                    <span class="text-xs font-semibold text-[#8a9099]"
                        >por {{ invite.created_by }} ·
                        {{ formatDayMonth(invite.created_at) }}</span
                    >
                </div>
                <InviteRedeemers role="cell" :invite="invite" />
                <InviteUsage role="cell" :invite="invite" />
                <span
                    role="cell"
                    class="text-sm font-semibold text-[#565d66]"
                    >{{
                        invite.expires_at
                            ? formatDayMonth(invite.expires_at)
                            : 'Nunca'
                    }}</span
                >
                <span role="cell"
                    ><InviteStatusPill :status="invite.status"
                /></span>
                <InviteActions role="cell" :invite="invite" />
            </div>

            <p
                v-if="!visibleInvites.length"
                class="text-faint-2 px-6 py-10 text-center text-sm font-semibold"
            >
                Nenhum convite nesse filtro.
            </p>
        </div>

        <ul class="flex flex-col gap-3 lg:hidden" aria-label="Convites">
            <li
                v-for="invite in visibleInvites"
                :key="invite.id"
                class="flex flex-col gap-3.5 rounded-3xl bg-white p-4 shadow-[0_2px_3px_rgba(28,32,38,0.04),0_10px_24px_rgba(28,32,38,0.06)]"
                :class="[
                    isFaded(invite) ? 'opacity-60' : '',
                    invite.id === freshId ? 'row-in' : '',
                ]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span
                            class="font-mono text-[16px] font-extrabold"
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
                    <InviteStatusPill :status="invite.status" />
                </div>
                <InviteRedeemers :invite="invite" />
                <div
                    class="border-line flex items-end justify-between gap-3 border-t pt-3.5"
                >
                    <div class="flex items-end gap-5">
                        <InviteUsage :invite="invite" />
                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-[11px] font-extrabold tracking-[0.12em] text-[#8a9099] uppercase"
                                >Expira</span
                            >
                            <span
                                class="text-[13px] font-extrabold text-[#565d66]"
                                >{{
                                    invite.expires_at
                                        ? formatDayMonth(invite.expires_at)
                                        : 'Nunca'
                                }}</span
                            >
                        </div>
                    </div>
                    <InviteActions :invite="invite" />
                </div>
            </li>
            <li
                v-if="!visibleInvites.length"
                class="text-faint-2 rounded-3xl bg-white px-6 py-10 text-center text-sm font-semibold"
            >
                Nenhum convite nesse filtro.
            </li>
        </ul>
    </AdminShell>
</template>

<style scoped>
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
