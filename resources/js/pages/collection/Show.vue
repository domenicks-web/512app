<script setup lang="ts">
import { computed } from 'vue';
import PokedexGrid from '@/components/collection/PokedexGrid.vue';
import type { SpeciesEntry } from '@/components/collection/PokedexGrid.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import SpecimenSheet from '@/components/pack/SpecimenSheet.vue';
import { useSpecimenSheet } from '@/composables/useSpecimenSheet';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { species as speciesRoute } from '@/routes/collection';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Pokédex do jogador: as 151 em ordem de dex. Espécie com espécime aparece
 * colorida (shiny se tiver algum), com quantos ele tem e a cor da
 * raridade; sem espécime vira silhueta. Tocar numa que ele tem abre a
 * ficha dos espécimes dela.
 */
const props = defineProps<{
    species: SpeciesEntry[];
    ownedSpecies: number;
    totalSpecimens: number;
}>();

const percent = computed(() =>
    props.species.length > 0
        ? Math.round((props.ownedSpecies / props.species.length) * 100)
        : 0,
);

/**
 * O Wayfinder tipa o `{species}` pela coluna do banco: `string` no MySQL
 * local, `number` no SQLite do CI. O id vai igual pros dois.
 */
const speciesKey = (id: number) =>
    id as unknown as Parameters<typeof speciesRoute.url>[0];

const sheet = useSpecimenSheet((id) => speciesRoute.url(speciesKey(id)));
</script>

<template>
    <GroupScreen
        title="Coleção"
        :subtitle="`${ownedSpecies} de ${species.length} espécies · ${totalSpecimens} espécimes`"
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

            <PokedexGrid
                :species="species"
                :loading-id="sheet.loadingId.value"
                empty-owned="Nenhuma espécie ainda. O pack de hoje te espera."
                empty-missing="Você já tem todas. Que coleção!"
                @open="sheet.open"
            />
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
        />
    </GroupScreen>
</template>
