<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import PokedexGrid from '@/components/collection/PokedexGrid.vue';
import type { SpeciesEntry } from '@/components/collection/PokedexGrid.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import SpecimenSheet from '@/components/pack/SpecimenSheet.vue';
import { useSpecimenSheet } from '@/composables/useSpecimenSheet';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { formatMint } from '@/lib/format';
import { CYAN } from '@/lib/pack/meta';
import { rarityLook } from '@/lib/pack/rarity';
import type { Tier } from '@/lib/pack/types';
import { show as compareShow } from '@/routes/compare';
import { accept, store } from '@/routes/friends/requests';
import { edit as profileEdit } from '@/routes/profile';
import { species as trainerSpecies } from '@/routes/trainer';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Cartão de Treinador (o Trainer Card dos jogos): quem é, os números, a
 * amizade com quem vê, o Time (os melhores espécimes) e a Pokédex. Tudo
 * vem pronto do servidor; tocar numa carta abre a ficha do dono.
 */
type TeamMember = {
    id: number;
    speciesId: number;
    speciesName: string;
    spritePath: string;
    isShiny: boolean;
    rarityTier: string;
    mintNumber: number;
    ivTotal: number;
};

const props = defineProps<{
    trainer: {
        id: number;
        nickname: string;
        tag: string;
        avatarSpritePath: string | null;
        memberSince: string;
        isViewer: boolean;
    };
    stats: {
        rankingPosition: number | null;
        shinyCount: number;
        openedPacks: number;
    };
    friendship: {
        state: 'self' | 'none' | 'outgoing' | 'incoming' | 'friends';
        friendshipId: number | null;
    };
    team: TeamMember[];
    collection: {
        species: SpeciesEntry[];
        ownedSpecies: number;
        totalSpecimens: number;
    };
}>();

const percent = computed(() =>
    props.collection.species.length > 0
        ? Math.round(
              (props.collection.ownedSpecies /
                  props.collection.species.length) *
                  100,
          )
        : 0,
);

const numbers = computed(() => [
    {
        label: 'Ranking',
        value:
            props.stats.rankingPosition === null
                ? '—'
                : `${props.stats.rankingPosition}º`,
    },
    { label: 'Shinies', value: String(props.stats.shinyCount) },
    { label: 'Packs', value: String(props.stats.openedPacks) },
]);

/** Mesmo truque da Coleção: o Wayfinder tipa `{species}` pela coluna do banco. */
type SpeciesArg = Extract<
    Parameters<typeof trainerSpecies.url>[0],
    { species: unknown }
>['species'];
const speciesKey = (id: number) => id as unknown as SpeciesArg;

const sheet = useSpecimenSheet((id) =>
    trainerSpecies.url({ user: props.trainer.id, species: speciesKey(id) }),
);

/** Melhor espécime de quem vê daquela espécie (vem com a ficha). */
const viewerSpecimenId = computed(() =>
    typeof sheet.extra.value.viewerSpecimenId === 'number'
        ? sheet.extra.value.viewerSpecimenId
        : null,
);

const isSending = ref(false);
const friendError = ref<string | null>(null);

function addFriend() {
    isSending.value = true;
    friendError.value = null;
    router.post(
        store().url,
        { handle: `${props.trainer.nickname}#${props.trainer.tag}` },
        {
            preserveScroll: true,
            onError: (errors) =>
                (friendError.value =
                    Object.values(errors)[0] ?? 'Não deu pra mandar o pedido.'),
            onFinish: () => (isSending.value = false),
        },
    );
}

function acceptFriend() {
    if (props.friendship.friendshipId === null) {
        return;
    }

    isSending.value = true;
    router.post(
        accept(props.friendship.friendshipId).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (isSending.value = false),
        },
    );
}

const look = (member: TeamMember) =>
    rarityLook(member.rarityTier as Tier, member.isShiny);

const card =
    'rounded-[22px] bg-white shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]';
const pill =
    'h-11 cursor-pointer rounded-full px-5 text-[14px] font-extrabold transition-transform active:scale-[0.98] disabled:opacity-60';
</script>

<template>
    <GroupScreen
        :title="trainer.isViewer ? 'Meu cartão' : trainer.nickname"
        subtitle="Cartão de Treinador"
    >
        <div class="flex flex-col gap-5">
            <section
                data-enter
                :class="card"
                class="flex flex-col overflow-hidden"
            >
                <div
                    class="flex items-center gap-4 bg-[linear-gradient(135deg,#f24d4d,#ff7a6b)] p-5 text-white"
                >
                    <div
                        class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.6),0_8px_18px_rgba(120,20,20,0.25)]"
                    >
                        <FittedSprite
                            v-if="trainer.avatarSpritePath"
                            :src="`/storage/${trainer.avatarSpritePath}`"
                            :size="80"
                            :padding="10"
                        />
                    </div>
                    <div class="flex min-w-0 flex-col gap-1">
                        <span
                            class="truncate text-[24px] leading-tight font-black tracking-[-0.02em]"
                            >{{ trainer.nickname
                            }}<span class="text-white/70"
                                >#{{ trainer.tag }}</span
                            ></span
                        >
                        <span class="text-[13px] font-bold text-white/85"
                            >Treinador desde {{ trainer.memberSince }}</span
                        >
                    </div>
                </div>

                <div class="grid grid-cols-3 divide-x divide-[#eceef0]">
                    <div
                        v-for="number in numbers"
                        :key="number.label"
                        class="flex flex-col items-center gap-0.5 py-4"
                    >
                        <span
                            class="text-ink text-[22px] leading-none font-black"
                            >{{ number.value }}</span
                        >
                        <span class="text-soft text-xs font-bold">{{
                            number.label
                        }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-2 border-t border-[#eceef0] p-5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-ink text-[15px] font-black"
                            >Pokédex</span
                        >
                        <span class="text-soft text-sm font-extrabold"
                            >{{ collection.ownedSpecies }} de
                            {{ collection.species.length }}</span
                        >
                    </div>
                    <div class="bg-base h-2 overflow-hidden rounded-full">
                        <div
                            class="bg-brand h-full rounded-full"
                            :style="{ width: `${percent}%` }"
                        />
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3 border-t border-[#eceef0] px-5 py-4"
                >
                    <template v-if="friendship.state === 'self'">
                        <span class="text-soft text-sm font-semibold"
                            >Este é o seu cartão, do jeito que o grupo vê.</span
                        >
                        <Link
                            :href="profileEdit()"
                            class="text-brand-ink text-sm font-extrabold"
                            >Editar perfil</Link
                        >
                    </template>
                    <button
                        v-else-if="friendship.state === 'none'"
                        type="button"
                        :disabled="isSending"
                        :class="pill"
                        class="bg-brand text-white shadow-[0_8px_20px_rgba(242,77,77,0.3)]"
                        @click="addFriend"
                    >
                        Adicionar amigo
                    </button>
                    <button
                        v-else-if="friendship.state === 'incoming'"
                        type="button"
                        :disabled="isSending"
                        :class="pill"
                        class="bg-brand text-white shadow-[0_8px_20px_rgba(242,77,77,0.3)]"
                        @click="acceptFriend"
                    >
                        Aceitar pedido de amizade
                    </button>
                    <span
                        v-else-if="friendship.state === 'outgoing'"
                        class="text-soft text-sm font-extrabold"
                        >Pedido de amizade enviado</span
                    >
                    <span
                        v-else
                        class="flex items-center gap-1.5 rounded-full bg-[rgba(63,143,107,0.14)] px-3 py-1.5 text-[13px] font-extrabold text-[#2f7a57]"
                        >✓ Amigos</span
                    >
                    <span
                        v-if="friendError"
                        role="alert"
                        class="text-brand-ink text-[13px] font-extrabold"
                        >{{ friendError }}</span
                    >
                </div>
            </section>

            <section v-if="team.length > 0" class="flex flex-col gap-3">
                <h2 data-enter class="text-ink px-1 text-[17px] font-black">
                    Time
                </h2>
                <ul data-enter class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                    <li v-for="member in team" :key="member.id">
                        <button
                            type="button"
                            class="flex w-full cursor-pointer flex-col items-center gap-1.5 transition-transform active:scale-[0.97]"
                            :aria-label="`${member.speciesName}${member.isShiny ? ' shiny' : ''} ${formatMint(member.mintNumber)}`"
                            @click="sheet.open(member.speciesId, member.id)"
                        >
                            <span
                                class="block w-full rounded-[18px] p-[2px]"
                                :style="{
                                    background: look(member).frame,
                                    boxShadow: look(member).shadow,
                                }"
                            >
                                <span
                                    class="flex aspect-square items-center justify-center overflow-hidden rounded-[16px]"
                                    :style="{ background: look(member).panel }"
                                >
                                    <FittedSprite
                                        :src="`/storage/${member.spritePath}`"
                                        :size="88"
                                        :padding="8"
                                    />
                                </span>
                            </span>
                            <span
                                class="text-ink w-full truncate text-center text-[13px] font-black"
                                >{{ member.speciesName }}</span
                            >
                            <span
                                class="text-faint-2 -mt-1 text-[11px] font-bold"
                                ><span
                                    v-if="member.isShiny"
                                    :style="{ color: CYAN }"
                                    >shiny · </span
                                >{{ formatMint(member.mintNumber) }}</span
                            >
                        </button>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-3">
                <h2 data-enter class="text-ink px-1 text-[17px] font-black">
                    Coleção
                </h2>
                <PokedexGrid
                    :species="collection.species"
                    :loading-id="sheet.loadingId.value"
                    :of-other="!trainer.isViewer"
                    :empty-owned="
                        trainer.isViewer
                            ? 'Nenhuma espécie ainda. O pack de hoje te espera.'
                            : `${trainer.nickname} ainda não tem nenhuma espécie.`
                    "
                    :empty-missing="
                        trainer.isViewer
                            ? 'Você já tem todas. Que coleção!'
                            : `${trainer.nickname} já tem todas!`
                    "
                    @open="sheet.open"
                />
            </section>
        </div>

        <SpecimenSheet
            :specimen="sheet.current.value"
            :position="{
                index: sheet.index.value,
                count: sheet.specimens.value.length,
            }"
            light
            @close="sheet.close"
            @prev="sheet.step(-1)"
            @next="sheet.step(1)"
        >
            <template #actions="{ specimen }">
                <Link
                    v-if="!trainer.isViewer && viewerSpecimenId !== null"
                    :href="
                        compareShow({
                            mine: viewerSpecimenId,
                            theirs: specimen.id,
                        })
                    "
                    class="bg-brand flex h-12 items-center justify-center rounded-full text-[15px] font-black text-white shadow-[0_8px_20px_rgba(242,77,77,0.3)] transition-transform active:scale-[0.98]"
                >
                    Comparar com o meu
                </Link>
            </template>
        </SpecimenSheet>
    </GroupScreen>
</template>
