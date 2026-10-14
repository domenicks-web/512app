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

const shortMonths = [
    'jan',
    'fev',
    'mar',
    'abr',
    'mai',
    'jun',
    'jul',
    'ago',
    'set',
    'out',
    'nov',
    'dez',
];

/** "06 nov", no fuso do navegador. */
export function formatDayMonth(iso: string): string {
    const date = new Date(iso);

    return `${String(date.getDate()).padStart(2, '0')} ${shortMonths[date.getMonth()]}`;
}

/** "05:12:33": horas, minutos e segundos que faltam até `iso` (nunca negativo). */
export function formatCountdown(iso: string, now: number): string {
    const seconds = Math.max(
        0,
        Math.floor((new Date(iso).getTime() - now) / 1000),
    );
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${pad(Math.floor(seconds / 3600))}:${pad(Math.floor(seconds / 60) % 60)}:${pad(seconds % 60)}`;
}
