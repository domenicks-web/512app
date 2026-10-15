<script setup lang="ts">
/**
 * Selo do mint Nº 1: o primeiro espécime daquela espécie no servidor. Uma
 * plaquinha dourada com estrela, na mesma língua visual do aviso
 * "Primeiro … do servidor" da cerimônia do pack.
 */
withDefaults(defineProps<{ size?: 'sm' | 'lg' }>(), { size: 'sm' });
</script>

<template>
    <span
        class="inline-flex items-center rounded-full bg-[linear-gradient(180deg,#FFE7A3_0%,#FFC94D_55%,#E9A935_100%)] leading-none font-black text-[#5a3d00] shadow-[inset_0_1px_0_rgba(255,255,255,0.7),0_0_0_1px_rgba(120,80,0,0.35),0_2px_5px_rgba(0,0,0,0.25)]"
        :class="
            size === 'lg'
                ? 'h-7 gap-1 pr-2.5 pl-2 text-[13px]'
                : 'h-[18px] gap-0.5 pr-1.5 pl-1 text-[10px]'
        "
        title="Primeiro do servidor"
        aria-label="Primeiro do servidor"
    >
        <svg
            :width="size === 'lg' ? 13 : 9"
            :height="size === 'lg' ? 13 : 9"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                d="M12 2l2.9 6.6 7.1.6-5.4 4.7 1.6 7L12 17.3 5.8 20.9l1.6-7L2 9.2l7.1-.6z"
            />
        </svg>
        1º
    </span>
</template>
