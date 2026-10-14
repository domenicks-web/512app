<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { inject, nextTick, onMounted, ref } from 'vue';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import { groupNavigationKey } from '@/layouts/groupNavigation';
import { animate, spring } from '@/lib/motion';
import { home } from '@/routes';

/**
 * Moldura das telas do grupo (Coleção, Ranking, Trocas, Amigos, Feed,
 * Perfil): voltar pra Home, título e conteúdo rolável. O dock mora no
 * `GroupLayout`, que fica parado entre as abas. Os filhos com `data-enter`
 * sobem em cascata só na primeira entrada; entre abas, a tela inteira
 * desliza (ver `GroupLayout`).
 */
const props = defineProps<{
    title: string;
    subtitle?: string;
}>();

const groupNavigation = inject(groupNavigationKey, null);
const field = useDotField();
const root = ref<HTMLElement | null>(null);

onMounted(async () => {
    if (groupNavigation?.isSlideNavigation()) {
        return;
    }

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

    <div ref="root" class="absolute inset-0 flex flex-col">
        <header
            data-enter
            class="mx-auto flex w-full max-w-[calc(880px+2*clamp(16px,5vw,56px))] items-center gap-4 px-[clamp(16px,5vw,56px)] pt-[22px] pb-4"
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
    </div>
</template>
