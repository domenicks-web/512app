<script setup lang="ts">
import { computed, ref } from 'vue';
import CollectionCard from '@/components/collection/CollectionCard.vue';

/**
 * As 151 em ordem de dex com o filtro Todas/Tenho/Faltam (Coleção e
 * Cartão de Treinador). Só a que tem espécime abre a ficha. O filtro só
 * muda o que é exibido.
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
};

const props = defineProps<{
    species: SpeciesEntry[];
    loadingId: number | null;
    emptyOwned: string;
    emptyMissing: string;
}>();
const emit = defineEmits<{ open: [speciesId: number] }>();

type Filter = 'all' | 'owned' | 'missing';

const FILTERS: { key: Filter; label: string }[] = [
    { key: 'all', label: 'Todas' },
    { key: 'owned', label: 'Tenho' },
    { key: 'missing', label: 'Faltam' },
];

const filter = ref<Filter>('all');

const visibleSpecies = computed(() =>
    props.species.filter((entry) => {
        if (filter.value === 'owned') {
            return entry.specimenCount > 0;
        }

        if (filter.value === 'missing') {
            return entry.specimenCount === 0;
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
            class="cursor-pointer rounded-full px-4 py-2 text-[13px] font-extrabold transition-colors"
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
        {{ filter === 'owned' ? emptyOwned : emptyMissing }}
    </p>
</template>
