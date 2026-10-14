<script setup lang="ts">
import GroupScreen from '@/components/group/GroupScreen.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Packs: espaço reservado. Abrir o pack do dia fica na pokébola vermelha
 * do dock; esta tela ganha conteúdo depois.
 */
</script>

<template>
    <GroupScreen title="Packs">
        <div
            data-enter
            class="flex flex-col items-center gap-3 rounded-[22px] bg-white px-6 py-12 text-center shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]"
        >
            <svg
                width="48"
                height="48"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#c3cad4"
                stroke-width="2"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M4 10.5a8 6.5 0 0 1 16 0z" />
                <path d="M4 13.5a8 6.5 0 0 0 16 0z" />
            </svg>
            <p class="text-ink text-[19px] font-black">Conteúdo futuro</p>
            <p class="text-soft max-w-[300px] text-sm font-semibold">
                Por enquanto, o pack do dia abre pela pokébola vermelha do dock.
            </p>
        </div>
    </GroupScreen>
</template>
