<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import FittedSprite from '@/components/FittedSprite.vue';
import LogoutUserController from '@/actions/App/Http/Controllers/Auth/LogoutUserController';
import { home } from '@/routes';
import { index as invitesIndex } from '@/routes/admin/invites';
import { index as playersIndex } from '@/routes/admin/players';
import { edit as profileEdit } from '@/routes/profile';
import { showToast, toastKey, toastMessage } from './toast';
import AdminIcon from './AdminIcon.vue';

/**
 * Moldura do painel admin (handoff `512 Admin`): logo, abas em pílula com
 * contadores, quem está logado e o toast. Só tema claro.
 */
defineProps<{
    title: string;
    counts: { invites: number; players: number };
}>();

const page = usePage();
const nickname = (page.props.auth.user.nickname as string | null) ?? 'Admin';
const tag = page.props.auth.user.tag as string | null;
const avatarSpritePath = computed(
    () => page.props.auth.avatarSpritePath ?? null,
);

const tabs = [
    {
        label: 'Convites',
        href: invitesIndex.url(),
        component: 'admin/Invites',
        count: 'invites' as const,
    },
    {
        label: 'Jogadores',
        href: playersIndex.url(),
        component: 'admin/Players',
        count: 'players' as const,
    },
];

watch(
    () => page.flash.toast,
    (message) => message && showToast(message),
    { immediate: true },
);

const menuOpen = ref(false);
const menu = ref<HTMLElement | null>(null);

function closeOnOutside(event: MouseEvent) {
    if (menu.value && !menu.value.contains(event.target as Node)) {
        menuOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', closeOnOutside));
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutside));
</script>

<template>
    <Head :title="`Admin · ${title}`" />

    <div class="entry-light bg-base text-ink min-h-screen font-sans">
        <div class="mx-auto max-w-[1180px]">
            <header
                class="flex flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-7"
            >
                <div class="flex items-center gap-3">
                    <Link
                        :href="home.url()"
                        class="block drop-shadow-[0_6px_14px_rgba(242,77,77,0.28)]"
                        aria-label="512 — voltar ao jogo"
                    >
                        <img
                            src="/logo.svg"
                            alt=""
                            width="84"
                            height="42"
                            class="h-[42px] w-auto"
                        />
                    </Link>
                    <span
                        class="text-soft text-[11px] font-extrabold tracking-[0.18em] uppercase"
                        >Admin</span
                    >
                </div>

                <nav
                    class="order-3 flex w-full justify-center sm:order-none sm:w-auto"
                    aria-label="Seções do admin"
                >
                    <div
                        class="flex rounded-full bg-white p-1 shadow-[0_2px_3px_rgba(28,32,38,0.05),0_8px_20px_rgba(28,32,38,0.06)]"
                    >
                        <Link
                            v-for="tab in tabs"
                            :key="tab.href"
                            :href="tab.href"
                            class="flex h-10 items-center gap-1.5 rounded-full px-[18px] text-sm font-extrabold transition-colors"
                            :class="
                                $page.component === tab.component
                                    ? 'bg-ink text-white'
                                    : 'hover:text-ink text-[#565d66]'
                            "
                            :aria-current="
                                $page.component === tab.component
                                    ? 'page'
                                    : undefined
                            "
                        >
                            {{ tab.label }}
                            <span class="text-xs opacity-70">{{
                                counts[tab.count]
                            }}</span>
                        </Link>
                    </div>
                </nav>

                <div ref="menu" class="relative">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-3 rounded-full text-left"
                        :aria-expanded="menuOpen"
                        aria-label="Menu da conta"
                        @click="menuOpen = !menuOpen"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-[0_0_0_3px_#fff,0_0_0_5px_#f24d4d,0_6px_14px_rgba(28,32,38,0.12)]"
                        >
                            <FittedSprite
                                v-if="avatarSpritePath"
                                :src="`/storage/${avatarSpritePath}`"
                                :size="44"
                                :padding="6"
                            />
                        </span>
                        <span class="hidden flex-col gap-px sm:flex">
                            <span class="text-ink text-[15px] font-black"
                                >{{ nickname
                                }}<span class="text-faint-2"
                                    >#{{ tag }}</span
                                ></span
                            >
                            <span class="text-soft text-xs font-semibold"
                                >Admin</span
                            >
                        </span>
                    </button>
                    <div
                        v-if="menuOpen"
                        class="absolute top-full right-0 z-30 mt-2 flex w-48 flex-col rounded-2xl bg-white p-1.5 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_16px_40px_rgba(28,32,38,0.16)]"
                    >
                        <Link
                            :href="profileEdit.url()"
                            class="hover:bg-field rounded-xl px-3 py-2.5 text-sm font-extrabold"
                            >Editar perfil</Link
                        >
                        <Link
                            :href="home.url()"
                            class="hover:bg-field rounded-xl px-3 py-2.5 text-sm font-extrabold"
                            >Voltar ao jogo</Link
                        >
                        <Form :action="LogoutUserController()">
                            <button
                                type="submit"
                                class="text-brand-ink hover:bg-field w-full cursor-pointer rounded-xl px-3 py-2.5 text-left text-sm font-extrabold"
                            >
                                Sair
                            </button>
                        </Form>
                    </div>
                </div>
            </header>

            <main class="flex flex-col gap-[22px] px-4 pt-3 pb-20 sm:px-7">
                <slot />
            </main>
        </div>

        <Transition name="admin-toast">
            <div
                v-if="toastMessage"
                :key="toastKey"
                role="status"
                class="admin-toast bg-ink fixed top-4 left-1/2 z-50 flex w-max max-w-[min(440px,calc(100vw-32px))] -translate-x-1/2 items-center gap-2.5 rounded-[22px] py-3 pr-5 pl-3 text-sm leading-snug font-extrabold text-white shadow-[0_12px_30px_rgba(28,31,38,0.3)] sm:top-auto sm:bottom-7"
            >
                <span
                    class="bg-online flex size-[22px] shrink-0 items-center justify-center rounded-full"
                >
                    <AdminIcon name="check" :size="12" />
                </span>
                {{ toastMessage }}
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.admin-toast-enter-active,
.admin-toast-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.3s cubic-bezier(0.2, 1.4, 0.4, 1);
}

/* No celular o aviso desce do topo; no desktop sobe do rodapé (handoff). */
.admin-toast-enter-from,
.admin-toast-leave-to {
    opacity: 0;
    transform: translate(-50%, -12px);
}

@media (min-width: 640px) {
    .admin-toast-enter-from,
    .admin-toast-leave-to {
        transform: translate(-50%, 12px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .admin-toast-enter-active,
    .admin-toast-leave-active {
        transition: opacity 0.2s ease;
    }
}
</style>
