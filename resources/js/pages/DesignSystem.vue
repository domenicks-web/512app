<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppChip from '@/components/AppChip.vue';
import AppToggle from '@/components/AppToggle.vue';
import BrandMark from '@/components/BrandMark.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import SpecimenCard from '@/components/SpecimenCard.vue';
import StatusBar from '@/components/StatusBar.vue';

const packSound = ref(true);
const darkMode = ref(false);

const swatches = [
    { name: 'Vermelho 512', hex: '#F24D4D', class: 'bg-brand' },
    { name: 'Blush', hex: '#FFD9D9', class: 'bg-brand-blush' },
    { name: 'Tinta', hex: '#2B2F36', class: 'bg-ink' },
    { name: 'Cinza base', hex: '#EFF0F2', class: 'bg-base' },
    { name: 'Tela escura', hex: '#1C1F26', class: 'bg-screen-dark' },
];
</script>

<template>
    <Head title="Design System" />

    <div class="bg-base min-h-screen p-8">
        <div class="mx-auto flex max-w-4xl flex-col gap-12">
            <h1 class="font-display text-ink text-3xl font-extrabold">
                Design System · 512
            </h1>

            <section class="flex flex-col gap-4">
                <h2
                    class="text-ink/50 text-sm font-bold tracking-wide uppercase"
                >
                    Marca
                </h2>
                <div
                    class="flex flex-wrap items-center gap-6 rounded-2xl bg-white p-6"
                >
                    <BrandMark variant="icon" :size="72" />
                    <BrandMark variant="icon" :size="40" />
                    <BrandMark variant="icon" :size="16" />
                    <BrandMark variant="lockup" />
                    <div class="bg-screen-dark rounded-2xl p-4">
                        <BrandMark variant="lockup" />
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2
                    class="text-ink/50 text-sm font-bold tracking-wide uppercase"
                >
                    Cor e tipografia
                </h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <div
                        v-for="swatch in swatches"
                        :key="swatch.hex"
                        class="flex flex-col gap-2"
                    >
                        <div class="h-16 rounded-xl" :class="swatch.class" />
                        <p class="text-ink text-xs font-semibold">
                            {{ swatch.name }}
                        </p>
                        <p class="text-ink/50 text-xs">{{ swatch.hex }}</p>
                    </div>
                </div>
                <div class="rounded-2xl bg-white p-6">
                    <p class="font-display text-ink text-2xl font-extrabold">
                        Baloo 2 — títulos e celebração
                    </p>
                    <p class="text-ink mt-2 font-sans text-lg font-bold">
                        Inter — numeral, rótulos e corpo
                    </p>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2
                    class="text-ink/50 text-sm font-bold tracking-wide uppercase"
                >
                    Botões
                </h2>
                <div
                    class="flex flex-wrap items-center gap-4 rounded-2xl bg-white p-6"
                >
                    <AppButton variant="primary">Abrir pack</AppButton>
                    <AppButton variant="secondary">Coleção</AppButton>
                    <AppButton variant="muted" disabled>Indisponível</AppButton>
                    <AppButton circular variant="secondary">A</AppButton>
                    <AppButton circular variant="secondary">B</AppButton>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2
                    class="text-ink/50 text-sm font-bold tracking-wide uppercase"
                >
                    Chips e raridade
                </h2>
                <div class="flex flex-col gap-4 rounded-2xl bg-white p-6">
                    <div class="flex flex-wrap gap-3">
                        <AppChip variant="dark">#0412</AppChip>
                        <AppChip variant="blush">1 em 512</AppChip>
                        <AppChip variant="base">Tamanho 34cm</AppChip>
                        <AppChip variant="base">Potencial 78</AppChip>
                    </div>
                    <ProgressBar :value="62" class="max-w-xs" />
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2
                    class="text-ink/50 text-sm font-bold tracking-wide uppercase"
                >
                    Card de criatura
                </h2>
                <div class="flex flex-wrap gap-4 rounded-2xl bg-white p-6">
                    <div class="w-32">
                        <SpecimenCard
                            rare
                            rarity-label="brilhante"
                            registration-number="#0412"
                            name="Pikachu"
                            :potential="78"
                            size-label="34cm"
                        />
                    </div>
                    <div class="w-32">
                        <SpecimenCard
                            rarity-label="comum"
                            registration-number="#1187"
                            name="Rattata"
                            :potential="41"
                            size-label="22cm"
                        />
                    </div>
                    <div class="w-32">
                        <SpecimenCard state="empty" />
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2
                    class="text-ink/50 text-sm font-bold tracking-wide uppercase"
                >
                    Barra de status e ajustes rápidos
                </h2>
                <div class="flex flex-col gap-4 rounded-2xl bg-white p-6">
                    <StatusBar time="13:30" is-self />
                    <div class="bg-base max-w-xs rounded-2xl p-4">
                        <AppToggle v-model="packSound" label="Som do pack" />
                        <div class="bg-ink/10 my-3 h-px" />
                        <AppToggle v-model="darkMode" label="Modo escuro" />
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
