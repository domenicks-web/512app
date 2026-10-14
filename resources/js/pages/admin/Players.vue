<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminIcon from '@/components/admin/AdminIcon.vue';
import AdminShell from '@/components/admin/AdminShell.vue';
import FilterChips from '@/components/admin/FilterChips.vue';
import PasswordDialog from '@/components/admin/PasswordDialog.vue';
import PlayerAvatar from '@/components/admin/PlayerAvatar.vue';
import PlayerMenu from '@/components/admin/PlayerMenu.vue';
import RoleDialog from '@/components/admin/RoleDialog.vue';
import { showToast } from '@/components/admin/toast';
import type { Player } from '@/components/admin/types';
import { formatDayMonth } from '@/lib/format';
import {
    index as playersIndex,
    quota as quotaRoute,
} from '@/routes/admin/players';

const props = defineProps<{
    players: Player[];
    search: string;
    counts: { invites: number; players: number };
    quota: { min: number; max: number };
}>();

const page = usePage();
const currentUserId = page.props.auth.user.id;

const filter = ref<'all' | 'admins'>('all');
const query = ref(props.search);
const roleTarget = ref<Player | null>(null);
const passwordTarget = ref<Player | null>(null);

const adminCount = computed(
    () => props.players.filter((player) => player.is_admin).length,
);
const filterOptions = computed(() => [
    { value: 'all' as const, label: 'Todos', count: props.players.length },
    { value: 'admins' as const, label: 'Admins', count: adminCount.value },
]);
const visiblePlayers = computed(() =>
    filter.value === 'admins'
        ? props.players.filter((player) => player.is_admin)
        : props.players,
);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            playersIndex.url(),
            value.trim() ? { q: value.trim() } : {},
            {
                only: ['players', 'search'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 250);
});

onBeforeUnmount(() => clearTimeout(searchTimer));

function isLastAdmin(player: Player): boolean {
    return player.is_admin && adminCount.value <= 1 && !props.search;
}

function setQuota(player: Player, value: number) {
    if (value < props.quota.min || value > props.quota.max) {
        return;
    }

    router
        .optimistic((pageProps: { players?: unknown }) => ({
            players: (pageProps.players as Player[]).map((item) =>
                item.id === player.id ? { ...item, invite_quota: value } : item,
            ),
        }))
        .patch(
            quotaRoute.url(player.id),
            { quota: value },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) =>
                    showToast(errors.quota ?? 'Não deu pra mudar a cota.'),
            },
        );
}

function joinedAt(player: Player): string {
    return player.created_at ? formatDayMonth(player.created_at) : '—';
}
</script>

<template>
    <AdminShell title="Jogadores" :counts="counts">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1.5">
                <h1
                    class="text-[34px] leading-[1.05] font-black tracking-[-0.03em]"
                >
                    Jogadores
                </h1>
                <p class="text-sm font-semibold text-[#6c737c]">
                    Admins geram convites sem cota, revogam códigos e promovem
                    outros jogadores.
                </p>
            </div>
            <label
                class="relative flex w-full min-w-[220px] sm:w-auto sm:flex-[0_1_300px]"
            >
                <span class="sr-only">Buscar jogador</span>
                <AdminIcon
                    name="search"
                    class="pointer-events-none absolute top-[17px] left-[18px] text-[#8a9099]"
                />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Buscar nickname ou email"
                    class="text-ink placeholder:text-placeholder h-[50px] w-full rounded-full bg-white pr-5 pl-11 text-sm font-semibold shadow-[0_2px_3px_rgba(28,32,38,0.05),0_8px_20px_rgba(28,32,38,0.06)] outline-none focus:shadow-[inset_0_0_0_2px_#f24d4d]"
                />
            </label>
        </div>

        <FilterChips v-model="filter" :options="filterOptions" />

        <div
            class="overflow-x-auto rounded-3xl bg-white shadow-[0_2px_3px_rgba(28,32,38,0.04),0_12px_32px_rgba(28,32,38,0.07)]"
        >
            <div class="min-w-[860px]" role="table" aria-label="Jogadores">
                <div
                    role="row"
                    class="player-grid border-line items-end border-b px-6 py-4 text-[11px] font-extrabold tracking-[0.12em] text-[#8a9099] uppercase"
                >
                    <span role="columnheader">Jogador</span>
                    <span role="columnheader">Entrou com</span>
                    <span role="columnheader">Desde</span>
                    <span role="columnheader">Espécimes</span>
                    <span role="columnheader">Cota de convites</span>
                    <span role="columnheader" class="text-right">Papel</span>
                    <span role="columnheader" class="sr-only">Mais</span>
                </div>

                <div
                    v-for="player in visiblePlayers"
                    :key="player.id"
                    role="row"
                    class="player-grid border-line items-center border-b px-6 py-4 transition-colors last:border-b-0 hover:bg-[#fafafb]"
                >
                    <div role="cell" class="flex min-w-0 items-center gap-3">
                        <PlayerAvatar
                            :sprite-path="player.avatar_sprite_path"
                            :name="player.nickname ?? player.email"
                            :is-admin="player.is_admin"
                        />
                        <div class="flex min-w-0 flex-col">
                            <div class="flex items-baseline gap-1">
                                <span
                                    class="text-[15px] font-black whitespace-nowrap"
                                    >{{ player.nickname ?? 'Sem perfil' }}</span
                                >
                                <span
                                    v-if="player.tag"
                                    class="text-faint-2 text-[13px] font-semibold"
                                    >#{{ player.tag }}</span
                                >
                                <span
                                    v-if="player.id === currentUserId"
                                    class="bg-base ml-1 rounded-full px-2 py-0.5 text-[11px] font-extrabold"
                                    >você</span
                                >
                            </div>
                            <span
                                class="truncate text-xs font-semibold text-[#8a9099]"
                                >{{ player.email }}</span
                            >
                        </div>
                    </div>

                    <div role="cell" class="flex min-w-0 flex-col gap-0.5">
                        <span class="font-mono text-[13px] font-extrabold">{{
                            player.invite_code ?? '–'
                        }}</span>
                        <span
                            class="truncate text-xs font-semibold text-[#8a9099]"
                            >{{
                                player.invite_code
                                    ? `convite de ${player.invited_by ?? '?'}`
                                    : 'Fundador'
                            }}</span
                        >
                    </div>

                    <span
                        role="cell"
                        class="text-sm font-semibold text-[#565d66]"
                        >{{ joinedAt(player) }}</span
                    >

                    <span role="cell" class="text-[15px] font-black">{{
                        player.specimens_count
                    }}</span>

                    <div role="cell">
                        <span
                            v-if="player.is_admin"
                            class="bg-field text-soft inline-flex h-9 items-center rounded-full px-4 text-[13px] font-extrabold"
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
                                :disabled="player.invite_quota <= quota.min"
                                aria-label="Menos convites"
                                @click="
                                    setQuota(player, player.invite_quota - 1)
                                "
                            >
                                −
                            </button>
                            <span
                                class="w-7 text-center text-sm font-black"
                                aria-live="polite"
                                >{{ player.invite_quota }}</span
                            >
                            <button
                                type="button"
                                class="flex size-[30px] cursor-pointer items-center justify-center rounded-full bg-white font-black shadow-[0_1px_3px_rgba(28,32,38,0.1)] disabled:cursor-default disabled:opacity-40"
                                :disabled="player.invite_quota >= quota.max"
                                aria-label="Mais convites"
                                @click="
                                    setQuota(player, player.invite_quota + 1)
                                "
                            >
                                +
                            </button>
                        </div>
                    </div>

                    <div role="cell" class="flex justify-end">
                        <button
                            v-if="
                                player.id === currentUserId ||
                                isLastAdmin(player)
                            "
                            type="button"
                            disabled
                            class="text-ink h-9 rounded-full bg-white px-4 text-[13px] font-extrabold opacity-50 shadow-[inset_0_0_0_2px_#e4e6ea]"
                            :title="
                                player.id === currentUserId
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
                            @click="roleTarget = player"
                        >
                            Remover admin
                        </button>
                        <button
                            v-else
                            type="button"
                            class="bg-ink h-9 cursor-pointer rounded-full px-4 text-[13px] font-extrabold whitespace-nowrap text-white"
                            @click="roleTarget = player"
                        >
                            Tornar admin
                        </button>
                    </div>

                    <div role="cell" class="flex justify-end">
                        <PlayerMenu
                            :player="player"
                            :is-self="player.id === currentUserId"
                            @change-password="passwordTarget = $event"
                        />
                    </div>
                </div>

                <p
                    v-if="!visiblePlayers.length"
                    class="text-faint-2 px-6 py-10 text-center text-sm font-semibold"
                >
                    Ninguém encontrado.
                </p>
            </div>
        </div>

        <RoleDialog :player="roleTarget" @close="roleTarget = null" />
        <PasswordDialog
            :player="passwordTarget"
            @close="passwordTarget = null"
        />
    </AdminShell>
</template>

<style scoped>
.player-grid {
    display: grid;
    grid-template-columns:
        minmax(190px, 1.6fr) minmax(110px, 1fr)
        64px 72px 116px 134px 36px;
    gap: 14px;
}
</style>
