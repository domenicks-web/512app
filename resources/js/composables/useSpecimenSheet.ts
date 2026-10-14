import { computed, ref } from 'vue';
import type { PackSpecimen } from '@/lib/pack/types';

/**
 * Ficha de espécimes de uma espécie (Coleção e Cartão de Treinador): busca
 * o JSON da espécie tocada e anda entre os espécimes, um de cada vez. O
 * resto do JSON (ex.: `viewerSpecimenId`) fica em `extra`.
 */
export function useSpecimenSheet(urlFor: (speciesId: number) => string) {
    const specimens = ref<PackSpecimen[]>([]);
    const index = ref(0);
    const loadingId = ref<number | null>(null);
    const extra = ref<Record<string, unknown>>({});
    const current = computed(() => specimens.value[index.value] ?? null);

    /** Abre a ficha da espécie; com `specimenId`, já no espécime dele. */
    async function open(speciesId: number, specimenId?: number) {
        if (loadingId.value !== null) {
            return;
        }

        loadingId.value = speciesId;

        try {
            const response = await fetch(urlFor(speciesId), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const { specimens: list, ...rest } = (await response.json()) as {
                specimens: PackSpecimen[];
            } & Record<string, unknown>;
            index.value = Math.max(
                0,
                list.findIndex((specimen) => specimen.id === specimenId),
            );
            extra.value = rest;
            specimens.value = list;
        } finally {
            loadingId.value = null;
        }
    }

    function step(delta: number) {
        const count = specimens.value.length;
        index.value = (index.value + delta + count) % count;
    }

    function close() {
        specimens.value = [];
    }

    return { specimens, index, current, loadingId, extra, open, step, close };
}
