<script setup lang="ts">
import { computed, ref } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';

defineOptions({ layout: EntryLayout });

/**
 * Pokédex do jogador: as 151 em ordem de dex. Espécie com espécime aparece
 * colorida (shiny se tiver algum), com quantos ele tem; sem espécime vira
 * silhueta. O filtro só muda o que é exibido.
 */
type SpeciesEntry = {
    id: number;
    name: string;
    rarityTier: string;
    spritePath: string;
    spriteShinyPath: string;
    specimenCount: number;
    shinyCount: number;
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

const dexNumber = (id: number) => `#${String(id).padStart(3, '0')}`;
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
                class="grid grid-cols-[repeat(auto-fill,minmax(96px,1fr))] gap-3"
            >
                <li
                    v-for="entry in visibleSpecies"
                    :key="entry.id"
                    class="relative flex flex-col items-center rounded-[18px] px-2 pt-2 pb-3"
                    :class="
                        entry.specimenCount > 0
                            ? 'bg-white shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]'
                            : 'bg-white/45'
                    "
                >
                    <span
                        class="text-faint absolute top-2 left-2.5 text-[11px] font-black tracking-[0.04em]"
                        >{{ dexNumber(entry.id) }}</span
                    >
                    <span
                        v-if="entry.shinyCount > 0"
                        class="text-shiny absolute top-1.5 right-1.5 rounded-full bg-[rgba(77,217,232,0.14)] px-1.5 py-0.5 text-[10px] font-black tracking-[0.06em] uppercase"
                        :title="`${entry.shinyCount} shiny`"
                        >Shiny</span
                    >
                    <img
                        :src="`/storage/${entry.shinyCount > 0 ? entry.spriteShinyPath : entry.spritePath}`"
                        :alt="entry.specimenCount > 0 ? entry.name : ''"
                        width="72"
                        height="72"
                        loading="lazy"
                        class="pixelated mt-3 size-[72px]"
                        :class="
                            entry.specimenCount > 0
                                ? ''
                                : 'opacity-[0.13] brightness-0'
                        "
                    />
                    <span
                        class="w-full truncate text-center text-[12.5px] font-extrabold"
                        :class="
                            entry.specimenCount > 0
                                ? 'text-ink'
                                : 'text-placeholder'
                        "
                        >{{
                            entry.specimenCount > 0 ? entry.name : '???'
                        }}</span
                    >
                    <span
                        v-if="entry.specimenCount > 1"
                        class="bg-ink absolute -right-1 -bottom-1 rounded-full px-2 py-0.5 text-[11px] font-black text-white"
                        >×{{ entry.specimenCount }}</span
                    >
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
    </GroupScreen>
</template>
