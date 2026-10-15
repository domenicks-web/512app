<script setup lang="ts">
import { formatDayMonth } from '@/lib/format';
import { shortHash } from '@/lib/pack/meta';
import type { PackSpecimen } from '@/lib/pack/types';

/**
 * Histórico de donos e hash da ficha. No computador ficam dentro da
 * ficha; no celular cada um abre num modal por cima dela, pra ficha
 * caber na tela (e poder inclinar inteira com o giroscópio).
 */
defineProps<{
    part: 'history' | 'hash';
    specimen: PackSpecimen;
    tone: {
        muted: string;
        soft: string;
        value: string;
        box: string;
        verified: string;
    };
    historyNickname: (specimen: PackSpecimen, k: number) => string | null;
}>();
</script>

<template>
    <div
        v-if="part === 'history' && specimen.history?.length"
        class="flex flex-col gap-2"
    >
        <div class="text-[11px] font-extrabold" :class="tone.muted">
            Histórico
        </div>
        <ol class="flex flex-col gap-1.5 border-l-2 border-[#e4e7eb] pl-3">
            <li
                v-for="(step, k) in specimen.history"
                :key="k"
                class="text-[13px] font-bold"
            >
                <span :style="{ color: tone.value }">{{
                    step.kind === 'caught'
                        ? `Capturado por ${step.nickname}`
                        : step.kind === 'released'
                          ? `Libertado: era de ${step.fromNickname}`
                          : `Trocado: ${step.fromNickname} → ${step.nickname}`
                }}</span>
                <span v-if="historyNickname(specimen, k)" :class="tone.soft">
                    · chamado
                    <span class="font-black">{{
                        historyNickname(specimen, k)
                    }}</span></span
                >
                <span :class="tone.muted">
                    · {{ formatDayMonth(step.at) }}</span
                >
            </li>
        </ol>
    </div>

    <div
        v-else-if="part === 'hash'"
        class="flex items-center justify-between gap-2.5 rounded-2xl px-3.5 py-3"
        :class="tone.box"
    >
        <div class="flex min-w-0 flex-col gap-[3px]">
            <div class="text-[11px] font-extrabold" :class="tone.muted">
                Hash do espécime
            </div>
            <div class="font-mono text-sm font-extrabold" :class="tone.soft">
                {{ shortHash(specimen.hash) }}
            </div>
        </div>
        <span
            class="flex h-[26px] items-center gap-1.5 rounded-full px-2.5 text-xs font-extrabold whitespace-nowrap"
            :class="tone.verified"
        >
            <svg
                width="11"
                height="11"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="3.4"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M5 12.5l4.5 4.5L19 7.5" /></svg
            >Verificado
        </span>
    </div>
</template>
