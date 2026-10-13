<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import LogoutUserController from '@/actions/App/Http/Controllers/Auth/LogoutUserController';
import { home } from '@/routes';
import { index as invitesIndex } from '@/routes/admin/invites';
import { index as usersIndex } from '@/routes/admin/users';
import ThemeToggle from '@/components/ThemeToggle.vue';

// Casca provisória do painel de admin: funcional, sem tratamento visual
// ainda. O design de verdade vem depois, junto das telas novas.
defineProps<{
    title: string;
}>();

const tabs = [
    { label: 'Convites', href: invitesIndex.url(), component: 'admin/Invites' },
    { label: 'Usuários', href: usersIndex.url(), component: 'admin/Users' },
];
</script>

<template>
    <Head :title="`Admin · ${title}`" />

    <div class="bg-base text-ink min-h-screen">
        <header
            class="border-ink/10 bg-surface flex flex-wrap items-center gap-4 border-b px-4 py-3 sm:px-6"
        >
            <span class="font-display text-lg font-extrabold">Admin</span>

            <nav class="flex gap-1">
                <Link
                    v-for="tab in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="rounded-full px-4 py-1.5 text-sm font-bold"
                    :class="
                        $page.component === tab.component
                            ? 'bg-brand text-white'
                            : 'text-ink/70 hover:bg-ink/5'
                    "
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-3">
                <Link
                    :href="home.url()"
                    class="text-ink/70 text-sm font-bold hover:underline"
                >
                    Voltar ao jogo
                </Link>
                <ThemeToggle />
                <Form :action="LogoutUserController()">
                    <button
                        type="submit"
                        class="text-ink/70 cursor-pointer text-sm font-bold hover:underline"
                    >
                        Sair
                    </button>
                </Form>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6 sm:px-6">
            <h1 class="font-display mb-6 text-2xl font-extrabold">
                {{ title }}
            </h1>
            <slot />
        </main>
    </div>
</template>
