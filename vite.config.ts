import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins, loadEnv } from 'vite-plus';
import type { Plugin } from 'vite-plus';

// `DEV_LAN_HOST` no .env (ex: 192.168.1.26) abre o dev server pra rede:
// celular e outros aparelhos acessam pelo IP do PC. Sem ele, só localhost.
const lanHost = loadEnv(
    process.env.NODE_ENV ?? 'development',
    process.cwd(),
    '',
).DEV_LAN_HOST;

/**
 * O SSR do Inertia põe no HTML o CSS dos componentes com a URL "local" do
 * Vite (`localhost:5173`) e marca o estilo como já carregado. No celular
 * esse localhost é o próprio celular: o CSS nunca chega. Com
 * `DEV_LAN_HOST`, a URL local passa a ser a da rede.
 */
function lanSsrOrigin(host: string): Plugin {
    return {
        name: '512:lan-ssr-origin',
        configureServer(server) {
            server.middlewares.use((_request, _response, next) => {
                const urls = server.resolvedUrls;
                const origin = `http://${host}:${server.config.server.port ?? 5173}/`;

                if (urls && urls.local[0] !== origin) {
                    urls.local = [origin];
                }

                next();
            });
        },
    };
}

export default defineConfig({
    plugins: lazyPlugins(() => [
        ...(lanHost ? [lanSsrOrigin(lanHost)] : []),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [600, 700, 800, 900],
                }),
                bunny('Baloo 2', {
                    weights: [700, 800],
                }),
                bunny('JetBrains Mono', {
                    weights: [800],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        ...(lanHost
            ? { host: '0.0.0.0', hmr: { host: lanHost }, cors: true }
            : {}),
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'design_handoff_512_onboarding/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'design_handoff_512_onboarding/**',
            'docs/superpowers/**',
            'CLAUDE.md',
            'docs/checkpoint.md',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
