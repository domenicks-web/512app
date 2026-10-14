import { ref } from 'vue';

/**
 * Toast do painel admin: um só na tela, some sozinho depois de 2,2s. O
 * `AdminShell` desenha; qualquer tela chama `showToast` (copiar, erro) e o
 * flash `toast` do servidor cai aqui também.
 */
export const toastMessage = ref<string | null>(null);
export const toastKey = ref(0);

let timer: ReturnType<typeof setTimeout> | undefined;

export function showToast(message: string): void {
    toastMessage.value = message;
    toastKey.value++;
    clearTimeout(timer);
    timer = setTimeout(() => (toastMessage.value = null), 2200);
}
