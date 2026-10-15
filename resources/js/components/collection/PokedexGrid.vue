<script setup lang="ts">
import { computed, ref } from 'vue';
import CollectionCard from '@/components/collection/CollectionCard.vue';

/**
 * As 151 em ordem de dex com o filtro Todas/Tenho/Faltam, abrindo em Tenho (Coleção e
 * Cartão de Treinador). Só a que tem espécime abre a ficha. O filtro só
 * muda o que é exibido. No cartão de outro, `commonIds` (as espécies de
 * quem vê) liga o "Em comum": as que os dois têm, pra comparar.
 */
export type SpeciesEntry = {
    id: number;
    name: string;
    rarityTier: string;
    spritePath: string;
    spriteShinyPath: string;
    specimenCount: number;
    shinyCount: number;
    bestMint: number | null;
    bestIv: number | null;
    /** Algum espécime atual desta espécie chegou por troca. */
    fromTrade?: boolean;
    /** Apelido, só quando há um espécime só da espécie. */
    nickname?: string | null;
};

const props = withDefaults(
    defineProps<{
        species: SpeciesEntry[];
        loadingId: number | null;
        emptyOwned: string;
        emptyMissing: string;
        /** Coleção de outro jogador: o filtro fala dele ("Tem"), não de mim. */
        ofOther?: boolean;
        commonIds?: number[] | null;
        emptyCommon?: string;
    }>(),
    {
        ofOther: false,
        commonIds: null,
        emptyCommon: 'Nenhuma espécie em comum.',
    },
);
const emit = defineEmits<{ open: [speciesId: number] }>();

type Filter = 'all' | 'owned' | 'missing' | 'common';

const common = computed(() =>
    props.commonIds === null ? null : new Set(props.commonIds),
);

const FILTERS = computed<{ key: Filter; label: string }[]>(() => [
    { key: 'all', label: 'Todas' },
    { key: 'owned', label: props.ofOther ? 'Tem' : 'Tenho' },
    { key: 'missing', label: props.ofOther ? 'Não tem' : 'Faltam' },
    ...(common.value === null
        ? []
        : [{ key: 'common' as const, label: 'Em comum' }]),
]);

const filter = ref<Filter>('owned');

const visibleSpecies = computed(() =>
    props.species.filter((entry) => {
        if (filter.value === 'owned') {
            return entry.specimenCount > 0;
        }

        if (filter.value === 'missing') {
            return entry.specimenCount === 0;
        }

        if (filter.value === 'common') {
            return entry.specimenCount > 0 && !!common.value?.has(entry.id);
        }

        return true;
    }),
);

function open(entry: SpeciesEntry) {
    if (entry.specimenCount > 0) {
        emit('open', entry.id);
    }
}
</script>

<template>
    <div
        data-enter
        role="radiogroup"
        aria-label="Filtrar espécies"
        class="bg-track/60 flex gap-1 self-start rounded-full p-1"
    >
        <button
            v-for="option in FILTERS"
            :key="option.key"
            type="button"
            role="radio"
            :aria-checked="filter === option.key"
            class="cursor-pointer rounded-full px-3 py-2 text-[13px] font-extrabold whitespace-nowrap transition-colors sm:px-4"
            :class="
                filter === option.key
                    ? 'text-ink bg-white shadow-[0_1px_3px_rgba(28,32,38,0.1)]'
                    : 'text-soft hover:text-ink'
            "
            @click="filter = option.key"
        >
            {{ option.label }}
        </button>
    </div>

    <ul
        data-enter
        class="grid grid-cols-[repeat(auto-fill,minmax(108px,1fr))] gap-3 sm:grid-cols-[repeat(auto-fill,minmax(128px,1fr))] sm:gap-4"
    >
        <li v-for="entry in visibleSpecies" :key="entry.id" class="flex">
            <CollectionCard
                :entry="entry"
                :loading="loadingId === entry.id"
                @open="open(entry)"
            />
        </li>
    </ul>

    <p
        v-if="visibleSpecies.length === 0"
        class="text-soft py-10 text-center text-sm font-semibold"
    >
        {{
            filter === 'owned'
                ? emptyOwned
                : filter === 'common'
                  ? emptyCommon
                  : emptyMissing
        }}
    </p>
</template>
