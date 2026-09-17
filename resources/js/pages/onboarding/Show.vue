<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Onboarding/CompleteOnboardingController';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/AppInput.vue';
import BrandMark from '@/components/BrandMark.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';

type AvatarOption = {
    id: number;
    name: string;
    artwork_path: string;
};

const props = defineProps<{
    avatarOptions: AvatarOption[];
}>();

const step = ref<1 | 2 | 3>(1);

const form = useForm({
    nickname: '',
    birthdate: '',
    avatar_species_id: null as number | null,
});

const canAdvanceFromStep1 = computed(() => form.nickname.trim().length >= 2);
const canAdvanceFromStep2 = computed(() => form.birthdate.length > 0);

function goToStep(target: 1 | 2 | 3) {
    step.value = target;
}

function submit() {
    form.post(store().url);
}
</script>

<template>
    <Head title="Complete seu perfil" />

    <div
        class="bg-base relative flex min-h-screen items-center justify-center overflow-hidden p-6"
    >
        <div
            class="bg-brand pointer-events-none absolute top-0 left-1/2 hidden h-[28rem] w-[28rem] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-20 blur-[120px] dark:block"
            aria-hidden="true"
        />

        <div class="absolute top-6 right-6">
            <ThemeToggle />
        </div>

        <div
            class="bg-surface ring-ink/10 relative w-full max-w-sm rounded-3xl p-8 shadow-xl ring-1 backdrop-blur-xl ring-inset"
        >
            <div class="flex flex-col items-center text-center">
                <BrandMark />
                <h1 class="font-display text-ink mt-6 text-2xl font-extrabold">
                    Quase lá
                </h1>
                <p class="text-ink/60 mt-2 text-sm">Passo {{ step }} de 3</p>
            </div>

            <form
                v-if="step === 1"
                class="mt-8 flex flex-col gap-4"
                @submit.prevent="goToStep(2)"
            >
                <AppInput
                    v-model="form.nickname"
                    label="Como quer ser chamado?"
                    name="nickname"
                    autocomplete="nickname"
                    required
                    :error="form.errors.nickname"
                />

                <AppButton
                    type="submit"
                    :disabled="!canAdvanceFromStep1"
                    class="mt-2 w-full"
                >
                    Continuar
                </AppButton>
            </form>

            <form
                v-else-if="step === 2"
                class="mt-8 flex flex-col gap-4"
                @submit.prevent="goToStep(3)"
            >
                <AppInput
                    v-model="form.birthdate"
                    label="Sua data de nascimento"
                    name="birthdate"
                    type="date"
                    autocomplete="bday"
                    required
                    :error="form.errors.birthdate"
                />

                <div class="mt-2 flex gap-3">
                    <AppButton
                        type="button"
                        variant="secondary"
                        class="w-full"
                        @click="goToStep(1)"
                    >
                        Voltar
                    </AppButton>
                    <AppButton
                        type="submit"
                        :disabled="!canAdvanceFromStep2"
                        class="w-full"
                    >
                        Continuar
                    </AppButton>
                </div>
            </form>

            <form
                v-else
                class="mt-8 flex flex-col gap-4"
                @submit.prevent="submit"
            >
                <p class="text-ink/70 text-sm font-bold">
                    Escolha seu avatar
                </p>

                <div class="grid grid-cols-5 gap-3">
                    <button
                        v-for="option in props.avatarOptions"
                        :key="option.id"
                        type="button"
                        class="ring-ink/10 flex aspect-square cursor-pointer items-center justify-center rounded-full bg-white p-2 ring-1 transition"
                        :class="
                            form.avatar_species_id === option.id
                                ? 'ring-brand ring-2'
                                : ''
                        "
                        @click="form.avatar_species_id = option.id"
                    >
                        <img
                            :src="`/storage/${option.artwork_path}`"
                            :alt="option.name"
                            class="h-full w-full object-contain"
                        />
                    </button>
                </div>

                <span
                    v-if="form.errors.avatar_species_id"
                    class="text-xs font-semibold text-red-500"
                    >{{ form.errors.avatar_species_id }}</span
                >

                <div class="mt-2 flex gap-3">
                    <AppButton
                        type="button"
                        variant="secondary"
                        class="w-full"
                        @click="goToStep(2)"
                    >
                        Voltar
                    </AppButton>
                    <AppButton
                        type="submit"
                        :disabled="
                            form.avatar_species_id === null || form.processing
                        "
                        class="w-full"
                    >
                        {{ form.processing ? 'Salvando...' : 'Entrar no jogo' }}
                    </AppButton>
                </div>
            </form>
        </div>
    </div>
</template>
