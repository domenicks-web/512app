import { createInertiaApp } from '@inertiajs/vue3';
import { installDeviceTilt } from '@/lib/deviceTilt';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});

// Giroscópio das cartas: só no navegador (o SSR não tem window).
installDeviceTilt();
