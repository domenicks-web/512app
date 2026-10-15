import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { getEcho } from '@/lib/realtime/echo';
import type { Notice } from '@/lib/realtime/notice';

/**
 * Ouve os avisos do jogador (`users.{id}`) e do grupo (`group`) enquanto o
 * layout do grupo está montado. Cada aviso entra na fila das bolhas e a
 * tela aberta recarrega só as props que ela tem entre as do aviso. O aviso
 * do grupo causado pelo próprio jogador é ignorado.
 */
export function useRealtime(userId: () => number | null) {
    const queue = ref<Notice[]>([]);
    const page = usePage();
    let joined: string[] = [];

    function receive(notice: Notice, fromGroup: boolean) {
        if (fromGroup && notice.actorId === userId()) {
            return;
        }

        queue.value.push(notice);
        const only = notice.reload.filter((key) => key in page.props);

        if (only.length > 0) {
            router.reload({ only });
        }
    }

    onMounted(() => {
        const echo = getEcho();
        const id = userId();

        if (!echo || id === null) {
            return;
        }

        joined = [`users.${id}`, 'group'];
        echo.private(`users.${id}`).listen('.notice', (notice: Notice) =>
            receive(notice, false),
        );
        echo.private('group').listen('.notice', (notice: Notice) =>
            receive(notice, true),
        );
    });

    onBeforeUnmount(() => {
        const echo = getEcho();
        joined.forEach((channel) => echo?.leave(channel));
        joined = [];
    });

    return { queue };
}
