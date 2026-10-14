import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Ref } from 'vue';

type PackStatus = { nextPackAt: string | null; cooldownHours: number } | null;

/**
 * Quanto falta pro próximo pack, de 0 (acabou de abrir) a 1 (disponível),
 * pelo `packStatus` compartilhado: vira a altura do vermelho que enche a
 * pokébola do dock e o tile do pack na Home. `now` é o relógio de quem usa.
 */
export function usePackProgress(now: Ref<number>) {
    const page = usePage<{ packStatus?: PackStatus }>();

    const progress = computed(() => {
        const status = page.props.packStatus;

        if (!status?.nextPackAt) {
            return 1;
        }

        const remaining = new Date(status.nextPackAt).getTime() - now.value;
        const cooldown = status.cooldownHours * 3600 * 1000;

        return Math.min(1, Math.max(0, 1 - remaining / cooldown));
    });

    return { progress, isReady: computed(() => progress.value >= 1) };
}
