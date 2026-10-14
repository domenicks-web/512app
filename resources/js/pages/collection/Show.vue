<script setup lang="ts">
import FittedSprite from '@/components/FittedSprite.vue';
import { computed, ref } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import SpecimenSheet from '@/components/pack/SpecimenSheet.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { CYAN, rgba, TIER_META } from '@/lib/pack/meta';
import type { PackSpecimen, Tier } from '@/lib/pack/types';
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

/** Intensidade do brilho da raridade no fundo da carta. */
const TIER_GLOW: Record<Tier, number> = {
    comum: 0.14,
    incomum: 0.24,
    raro: 0.3,
    lendario: 0.4,
};
/** Anel da carta: fino nas comuns, forte (com brilho) nas raras. */
const TIER_RING: Record<Tier, number> = {
    comum: 0.35,
    incomum: 0.75,
    raro: 1,
    lendario: 1,
};

function cardStyle(entry: SpeciesEntry): Record<string, string> {
    if (entry.specimenCount === 0) {
        return {};
    }

    const tier = entry.rarityTier as Tier;
    const shiny = entry.shinyCount > 0;
    const glow = shiny ? CYAN : TIER_META[tier].glow;
    const ringColor = shiny
        ? CYAN
        : tier === 'comum'
          ? '#9aa0a8'
          : TIER_META[tier].color;
    const strong = shiny || tier === 'raro' || tier === 'lendario';
    const lift = strong
        ? `0 10px 24px ${rgba(ringColor, 0.3)}`
        : '0 8px 18px rgba(28,32,38,0.07)';

    return {
        background: `radial-gradient(80% 65% at 50% 42%, ${rgba(glow, TIER_GLOW[tier] ?? 0.14)}, ${rgba(glow, 0)} 100%), #ffffff`,
        boxShadow: `0 0 0 2px ${rgba(ringColor, shiny ? 1 : (TIER_RING[tier] ?? 0.35))}, ${lift}`,
    };
}

/** Rótulo da raridade embaixo do nome, na cor do tier (legível no branco). */
function tierLabel(entry: SpeciesEntry): { text: string; color: string } {
    if (entry.shinyCount > 0) {
        return { text: 'Shiny', color: '#1fa9b8' };
    }

    const tier = entry.rarityTier as Tier;
    const color =
        tier === 'comum'
            ? '#8a9099'
            : tier === 'lendario'
              ? '#c99a1c'
              : TIER_META[tier].color;

    return { text: TIER_META[tier].label, color };
}

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
                class="grid grid-cols-[repeat(auto-fill,minmax(96px,1fr))] gap-3"
            >
                <li
                    v-for="entry in visibleSpecies"
                    :key="entry.id"
                    class="flex"
                >
                    <component
                        :is="entry.specimenCount > 0 ? 'button' : 'div'"
                        :type="entry.specimenCount > 0 ? 'button' : undefined"
                        class="relative flex w-full flex-col items-center rounded-[18px] px-2 pt-2 pb-3 transition-transform"
                        :class="[
                            entry.specimenCount > 0
                                ? 'cursor-pointer hover:-translate-y-0.5 active:scale-[0.97]'
                                : 'bg-white/45',
                            loadingId === entry.id ? 'opacity-60' : '',
                        ]"
                        :style="cardStyle(entry)"
                        :aria-label="
                            entry.specimenCount > 0
                                ? `Ver ${entry.name}`
                                : undefined
                        "
                        @click="openSpecies(entry)"
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
                        <FittedSprite
                            :src="`/storage/${entry.shinyCount > 0 ? entry.spriteShinyPath : entry.spritePath}`"
                            :alt="entry.specimenCount > 0 ? entry.name : ''"
                            :size="72"
                            :padding="6"
                            class="mt-3"
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
                            v-if="entry.specimenCount > 0"
                            class="mt-1 text-[10px] font-black tracking-[0.08em] uppercase"
                            :style="{ color: tierLabel(entry).color }"
                            >{{ tierLabel(entry).text }}</span
                        >
                        <span
                            v-if="entry.specimenCount > 1"
                            class="bg-ink absolute -right-1 -bottom-1 rounded-full px-2 py-0.5 text-[11px] font-black text-white"
                            >×{{ entry.specimenCount }}</span
                        >
                    </component>
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
            @close="sheetSpecimens = []"
            @prev="step(-1)"
            @next="step(1)"
        />
    </GroupScreen>
</template>
