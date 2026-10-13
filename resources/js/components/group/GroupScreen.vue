<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import HomeDock from '@/components/entry/HomeDock.vue';
import type { DockKey } from '@/components/entry/dock';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import { animate, spring } from '@/lib/motion';
import { home } from '@/routes';

/**
 * Moldura das telas que saem do dock (Coleção, Ranking, Trocas, Amigos):
 * voltar pra Home, título, conteúdo rolável e o mesmo dock embaixo, com o
 * item da tela marcado. Os filhos com `data-enter` sobem em cascata, igual
 * à entrada da Home.
 */
const props = defineProps<{
    title: string;
    subtitle?: string;
    active: DockKey;
}>();

const page = usePage<{ auth: { user: { is_admin: boolean } | null } }>();
const isAdmin = computed(() => Boolean(page.props.auth.user?.is_admin));

const field = useDotField();
const root = ref<HTMLElement | null>(null);

onMounted(async () => {
    await waitForIntro();
    field.hide();
    await nextTick();

    root.value
        ?.querySelectorAll<HTMLElement>('[data-enter]')
        .forEach((element, index) => {
            animate(
                element,
                [
                    { transform: 'translateY(28px)', opacity: 0 },
                    { transform: 'none', opacity: 1 },
                ],
                800,
                {
                    delay: Math.min(index, 12) * 45,
                    easing: spring(170, 16),
                },
            );
        });
});
</script>

<template>
    <Head :title="props.title" />

    <div ref="root" class="absolute inset-0 z-[3] flex flex-col">
        <header
            data-enter
            class="flex items-center gap-4 px-[clamp(16px,5vw,56px)] pt-[22px] pb-4"
        >
            <Link
                :href="home()"
                aria-label="Voltar pro início"
                class="text-ink flex size-11 shrink-0 items-center justify-center rounded-full bg-white shadow-[0_1px_2px_rgba(28,32,38,0.06),0_6px_14px_rgba(28,32,38,0.08)] transition-transform hover:-translate-x-0.5 active:scale-95"
            >
                <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M15 5l-7 7 7 7" />
                </svg>
            </Link>
            <div class="flex min-w-0 flex-col gap-0.5">
                <h1
                    class="text-ink text-[27px] leading-[1.1] font-black tracking-[-0.025em]"
                >
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="text-soft text-sm font-semibold">
                    {{ subtitle }}
                </p>
            </div>
            <div class="ml-auto">
                <slot name="aside" />
            </div>
        </header>

        <main
            class="min-h-0 flex-1 overflow-y-auto px-[clamp(16px,5vw,56px)] pt-2 pb-8"
        >
            <div class="mx-auto w-full max-w-[880px]">
                <slot />
            </div>
        </main>

        <div class="flex justify-center px-4 pt-2 pb-[18px]">
            <div data-enter>
                <HomeDock :is-admin="isAdmin" :active="active" />
            </div>
        </div>
    </div>
</template>
