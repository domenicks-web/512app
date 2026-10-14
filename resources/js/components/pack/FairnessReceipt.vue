<script setup lang="ts">
import { CYAN, TIER_META } from '@/lib/pack/meta';
import type { PackSpecimen, Receipt } from '@/lib/pack/types';
import PackModal from './PackModal.vue';

/** Comprovante: hash mostrado antes, server seed revelada e o HMAC de cada slot. */
defineProps<{
    open: boolean;
    receipt: Receipt | null;
    specimens: PackSpecimen[];
}>();
const emit = defineEmits<{ close: [] }>();

const dotColor = (specimen: PackSpecimen) =>
    specimen.isShiny
        ? CYAN
        : specimen.tier === 'comum'
          ? '#7d8794'
          : TIER_META[specimen.tier].color;
</script>

<template>
    <PackModal
        :open="open && receipt !== null"
        label="Comprovante do sorteio"
        max-width="640px"
        @close="emit('close')"
    >
        <div
            v-if="receipt"
            class="box-border flex flex-col gap-5 bg-[radial-gradient(70%_45%_at_50%_0%,rgba(63,143,107,0.16),rgba(63,143,107,0)_100%)] px-7 pt-[30px] pb-[26px]"
        >
            <div class="flex flex-col gap-1.5 pr-10">
                <div
                    class="text-[11px] font-extrabold tracking-[0.18em] text-[#7d8794] uppercase"
                >
                    Comprovante · nonce {{ receipt.nonce }}
                </div>
                <div class="text-[28px] font-black tracking-[-0.03em]">
                    O sorteio confere
                </div>
                <div
                    class="text-sm leading-normal font-semibold text-pretty text-[#c3cad4]"
                >
                    O hash foi mostrado antes da abertura. A server seed
                    revelada agora gera o mesmo hash, e cada slot sai de
                    HMAC-SHA256(server_seed, "client_seed:nonce:slot:n").
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <div class="text-[11px] font-extrabold text-[#7d8794]">
                        Hash do servidor (antes)
                    </div>
                    <div
                        class="font-mono text-xs leading-normal font-extrabold break-all text-[#c3cad4] select-text"
                    >
                        {{ receipt.serverSeedHash }}
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <div class="text-[11px] font-extrabold text-[#7d8794]">
                        Server seed (revelada)
                    </div>
                    <div
                        class="font-mono text-xs leading-normal font-extrabold break-all text-[#c3cad4] select-text"
                    >
                        {{ receipt.serverSeed }}
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex min-w-0 flex-col gap-1">
                        <div class="text-[11px] font-extrabold text-[#7d8794]">
                            Client seed
                        </div>
                        <div
                            class="font-mono text-[13px] font-extrabold break-all text-[#c3cad4]"
                        >
                            {{ receipt.clientSeed }}
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <div class="text-[11px] font-extrabold text-[#7d8794]">
                            Nonce
                        </div>
                        <div
                            class="font-mono text-[13px] font-extrabold text-[#c3cad4]"
                        >
                            {{ receipt.nonce }}
                        </div>
                    </div>
                </div>
                <div
                    class="flex items-center gap-2.5 rounded-2xl bg-[rgba(63,143,107,0.16)] px-3.5 py-3 text-[13px] font-extrabold text-[#6fd0a2]"
                >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3.2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M5 12.5l4.5 4.5L19 7.5" />
                    </svg>
                    sha256(server_seed) = hash do servidor
                </div>
            </div>
            <div
                class="flex flex-col overflow-hidden rounded-[18px] bg-white/4"
            >
                <div
                    v-for="specimen in specimens"
                    :key="specimen.id"
                    class="grid grid-cols-[54px_minmax(0,1fr)_auto] items-center gap-3 border-t border-white/5 px-3.5 py-[11px] first:border-t-0"
                >
                    <div class="text-[11px] font-extrabold text-[#7d8794]">
                        Slot {{ specimen.slot }}
                    </div>
                    <div
                        class="truncate font-mono text-xs font-extrabold text-[#7d8794]"
                    >
                        {{ specimen.seed.slice(0, 16) }}…
                    </div>
                    <div
                        class="flex items-center gap-2 text-[13px] font-extrabold"
                    >
                        <span
                            class="size-2 rounded-full"
                            :style="{ background: dotColor(specimen) }"
                        />{{
                            specimen.isShiny
                                ? `${specimen.name} shiny`
                                : specimen.name
                        }}
                    </div>
                </div>
            </div>
        </div>
    </PackModal>
</template>
