<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminIcon from '@/components/admin/AdminIcon.vue';
import AdminShell from '@/components/admin/AdminShell.vue';
import FilterChips from '@/components/admin/FilterChips.vue';
import PasswordDialog from '@/components/admin/PasswordDialog.vue';
import PlayerIdentity from '@/components/admin/PlayerIdentity.vue';
import PlayerMenu from '@/components/admin/PlayerMenu.vue';
import QuotaStepper from '@/components/admin/QuotaStepper.vue';
import RoleButton from '@/components/admin/RoleButton.vue';
import RoleDialog from '@/components/admin/RoleDialog.vue';
import type { Player } from '@/components/admin/types';
import { formatDayMonth } from '@/lib/format';
import { index as playersIndex } from '@/routes/admin/players';

/**
 * Jogadores: tabela do handoff a partir de 1024px; abaixo disso cada
 * jogador vira um cartão (nada de rolagem horizontal no celular).
 */
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

function isSelf(player: Player): boolean {
    return player.id === currentUserId;
}

/** Com busca ativa a lista não tem todos os admins: o servidor segura a regra. */
function isLastAdmin(player: Player): boolean {
    return player.is_admin && adminCount.value <= 1 && !props.search;
}

function origin(player: Player): string {
    return player.invite_code
        ? `convite de ${player.invited_by ?? '?'}`
        : 'Fundador';
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
            <label class="relative flex w-full sm:w-[300px]">
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
            class="hidden rounded-3xl bg-white shadow-[0_2px_3px_rgba(28,32,38,0.04),0_12px_32px_rgba(28,32,38,0.07)] lg:block"
            role="table"
            aria-label="Jogadores"
        >
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
                <PlayerIdentity
                    role="cell"
                    :player="player"
                    :is-self="isSelf(player)"
                />
                <div role="cell" class="flex min-w-0 flex-col gap-0.5">
                    <span class="font-mono text-[13px] font-extrabold">{{
                        player.invite_code ?? '–'
                    }}</span>
                    <span
                        class="truncate text-xs font-semibold text-[#8a9099]"
                        >{{ origin(player) }}</span
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
                    <QuotaStepper :player="player" :range="quota" />
                </div>
                <div role="cell" class="flex justify-end">
                    <RoleButton
                        :player="player"
                        :is-self="isSelf(player)"
                        :is-last-admin="isLastAdmin(player)"
                        @open="roleTarget = $event"
                    />
                </div>
                <div role="cell" class="flex justify-end">
                    <PlayerMenu
                        :player="player"
                        :is-self="isSelf(player)"
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

        <ul class="flex flex-col gap-3 lg:hidden" aria-label="Jogadores">
            <li
                v-for="player in visiblePlayers"
                :key="player.id"
                class="flex flex-col gap-3.5 rounded-3xl bg-white p-4 shadow-[0_2px_3px_rgba(28,32,38,0.04),0_10px_24px_rgba(28,32,38,0.06)]"
            >
                <div class="flex items-start justify-between gap-2">
                    <PlayerIdentity
                        :player="player"
                        :is-self="isSelf(player)"
                    />
                    <PlayerMenu
                        :player="player"
                        :is-self="isSelf(player)"
                        @change-password="passwordTarget = $event"
                    />
                </div>

                <dl class="grid grid-cols-3 gap-2">
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <dt
                            class="text-[11px] font-extrabold tracking-[0.1em] text-[#8a9099] uppercase"
                        >
                            Entrou com
                        </dt>
                        <dd
                            class="truncate font-mono text-[13px] font-extrabold"
                        >
                            {{ player.invite_code ?? origin(player) }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt
                            class="text-[11px] font-extrabold tracking-[0.1em] text-[#8a9099] uppercase"
                        >
                            Desde
                        </dt>
                        <dd class="text-[13px] font-extrabold text-[#565d66]">
                            {{ joinedAt(player) }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt
                            class="text-[11px] font-extrabold tracking-[0.1em] text-[#8a9099] uppercase"
                        >
                            Espécimes
                        </dt>
                        <dd class="text-[15px] font-black">
                            {{ player.specimens_count }}
                        </dd>
                    </div>
                </dl>

                <div
                    class="border-line flex flex-wrap items-center justify-between gap-3 border-t pt-3.5"
                >
                    <div class="flex items-center gap-2.5">
                        <span
                            class="text-[11px] font-extrabold tracking-[0.1em] text-[#8a9099] uppercase"
                            >Cota</span
                        >
                        <QuotaStepper :player="player" :range="quota" />
                    </div>
                    <RoleButton
                        :player="player"
                        :is-self="isSelf(player)"
                        :is-last-admin="isLastAdmin(player)"
                        @open="roleTarget = $event"
                    />
                </div>
            </li>
            <li
                v-if="!visiblePlayers.length"
                class="text-faint-2 rounded-3xl bg-white px-6 py-10 text-center text-sm font-semibold"
            >
                Ninguém encontrado.
            </li>
        </ul>

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
        64px 72px 116px 140px 36px;
    gap: 14px;
}
</style>
