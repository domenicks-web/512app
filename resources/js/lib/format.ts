/** Número de registro do espécime como aparece no jogo: `#0004`. */
export function formatMint(mintNumber: number): string {
    return `#${String(mintNumber).padStart(4, '0')}`;
}

/** "1 amigo online", "3 amigos online", "ninguém online agora". */
export function formatFriendsOnline(count: number): string {
    if (count === 0) {
        return 'ninguém online agora';
    }

    return `${count} ${count === 1 ? 'amigo' : 'amigos'} online`;
}
