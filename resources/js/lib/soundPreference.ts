/**
 * Preferência de som do jogador, guardada neste aparelho (vale pra
 * abertura do pack e pra qualquer som futuro). Sem localStorage (aba
 * anônima, bloqueio), cai no padrão: som ligado.
 */
const MUTE_KEY = 'pack:muted';

export function isSoundMuted(): boolean {
    try {
        return localStorage.getItem(MUTE_KEY) === '1';
    } catch {
        return false;
    }
}

export function setSoundMuted(muted: boolean): void {
    try {
        localStorage.setItem(MUTE_KEY, muted ? '1' : '0');
    } catch {
        // sem localStorage: vale só nesta visita
    }
}
