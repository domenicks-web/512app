<script setup lang="ts">
import { computed, ref } from 'vue';
import CollectionCard from '@/components/collection/CollectionCard.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import SpecimenSheet from '@/components/pack/SpecimenSheet.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import type { PackSpecimen } from '@/lib/pack/types';
import { species as speciesRoute } from '@/routes/collection';

defineOptions({ layout: EntryLayout });

/**
 * Pokédex do jogador: as 151 em ordem de dex. Espécie com espécime aparece
 * colorida (shiny se tiver algum), com quantos ele tem e a cor da
 * raridade; sem espécime vira silhueta. Tocar numa que ele tem abre a
 * ficha dos espécimes dela. O filtro só muda o que é exibido.
 */
type SpeciesEntry = {
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
    ownedSpecies: number;
    totalSpecimens: number;
}>();

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

const percent = computed(() =>
    props.species.length > 0
        ? Math.round((props.ownedSpecies / props.species.length) * 100)
        : 0,
);

// Ficha: os espécimes da espécie tocada, um de cada vez.
const sheetSpecimens = ref<PackSpecimen[]>([]);
const sheetIndex = ref(0);
const loadingId = ref<number | null>(null);
const sheetSpecimen = computed(
    () => sheetSpecimens.value[sheetIndex.value] ?? null,
);

async function openSpecies(entry: SpeciesEntry) {
    if (entry.specimenCount === 0 || loadingId.value !== null) {
        return;
    }

    loadingId.value = entry.id;

    try {
        const response = await fetch(speciesRoute.url(String(entry.id)), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        const data = (await response.json()) as { specimens: PackSpecimen[] };
        sheetIndex.value = 0;
        sheetSpecimens.value = data.specimens;
    } finally {
        loadingId.value = null;
    }
}

function step(delta: number) {
    const count = sheetSpecimens.value.length;
    sheetIndex.value = (sheetIndex.value + delta + count) % count;
}
</script>

<template>
    <GroupScreen
        title="Coleção"
        :subtitle="`${ownedSpecies} de ${species.length} espécies · ${totalSpecimens} espécimes`"
        active="collection"
    >
        <div class="flex flex-col gap-5">
            <section
                data-enter
                class="flex flex-col gap-3 rounded-[22px] bg-white p-5 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)]"
            >
                <div class="flex items-baseline gap-1">
                    <span
                        class="text-ink text-[48px] leading-[0.9] font-black tracking-[-0.03em]"
                        >{{ ownedSpecies }}</span
                    >
                    <span class="text-faint-2 text-lg font-black"
                        >/{{ species.length }}</span
                    >
                    <span class="text-soft ml-auto text-sm font-extrabold"
                        >{{ percent }}%</span
                    >
                </div>
                <div class="bg-base h-2 overflow-hidden rounded-full">
                    <div
                        class="bg-brand h-full rounded-full"
                        :style="{ width: `${percent}%` }"
                    />
                </div>
            </section>

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
                <li
                    v-for="entry in visibleSpecies"
                    :key="entry.id"
                    class="flex"
                >
                    <CollectionCard
                        :entry="entry"
                        :loading="loadingId === entry.id"
                        @open="openSpecies(entry)"
                    />
                </li>
            </ul>

            <p
                v-if="visibleSpecies.length === 0"
                class="text-soft py-10 text-center text-sm font-semibold"
            >
                {{
                    filter === 'owned'
                        ? 'Nenhuma espécie ainda. O pack de hoje te espera.'
                        : 'Você já tem todas. Que coleção!'
                }}
            </p>
        </div>

        <SpecimenSheet
            :specimen="sheetSpecimen"
            :position="{ index: sheetIndex, count: sheetSpecimens.length }"
            light
            @close="sheetSpecimens = []"
            @prev="step(-1)"
            @next="step(1)"
        />
    </GroupScreen>
</template>
