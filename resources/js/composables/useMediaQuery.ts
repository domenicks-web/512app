import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';

/** `true` enquanto a media query bater. No servidor (SSR) começa `false`. */
export function useMediaQuery(query: string): Ref<boolean> {
    const matches = ref(false);
    let list: MediaQueryList | null = null;
    const update = () => (matches.value = list?.matches ?? false);

    onMounted(() => {
        list = window.matchMedia(query);
        update();
        list.addEventListener('change', update);
    });
    onBeforeUnmount(() => list?.removeEventListener('change', update));

    return matches;
}
