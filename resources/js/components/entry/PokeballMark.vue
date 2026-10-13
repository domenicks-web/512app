<script setup lang="ts">
import { useId } from 'vue';

// Ícone de 22px dos botões primários: círculo branco cheio com a faixa e o
// anel vazados por máscara. Id único por instância (o protótipo repetia
// `pb512`, o que quebra a máscara quando há mais de um na tela).
const maskId = `pokeball-mask-${useId()}`;
</script>

<template>
    <svg
        width="22"
        height="22"
        viewBox="0 0 24 24"
        fill="none"
        class="shrink-0"
        aria-hidden="true"
    >
        <mask :id="maskId">
            <rect width="24" height="24" fill="#ffffff" />
            <rect x="0" y="10.9" width="24" height="2.2" fill="#000000" />
            <circle cx="12" cy="12" r="4.6" fill="#000000" />
            <circle cx="12" cy="12" r="2.4" fill="#ffffff" />
        </mask>
        <circle
            cx="12"
            cy="12"
            r="10"
            fill="currentColor"
            :mask="`url(#${maskId})`"
        />
    </svg>
</template>
