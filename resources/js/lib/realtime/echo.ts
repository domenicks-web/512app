import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { ChannelAuthorizationData } from 'pusher-js/types/src/core/auth/options';

/**
 * Conexão com o Reverb, criada só no navegador e na primeira vez que
 * alguém pede (nunca no SSR). Sem chave no `.env` (ex.: CI), não conecta.
 * A autorização dos canais privados manda o cookie XSRF no cabeçalho, como
 * o resto do app (não tem meta de CSRF no HTML).
 */
let echo: Echo<'reverb'> | null = null;

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function getEcho(): Echo<'reverb'> | null {
    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

    if (typeof window === 'undefined' || !key) {
        return null;
    }

    if (echo) {
        return echo;
    }

    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
    const scheme =
        (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'http';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost:
            (import.meta.env.VITE_REVERB_HOST as string | undefined) ||
            window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel: { name: string }) => ({
            authorize: (
                socketId: string,
                callback: (
                    error: Error | null,
                    data: ChannelAuthorizationData | null,
                ) => void,
            ) => {
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then((response) =>
                        response.ok
                            ? response.json()
                            : Promise.reject(new Error(`${response.status}`)),
                    )
                    .then((data: ChannelAuthorizationData) =>
                        callback(null, data),
                    )
                    .catch((error: Error) => callback(error, null));
            },
        }),
    });

    return echo;
}
