<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Onboarding/CompleteOnboardingController';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/AppInput.vue';
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

const headlines: Record<1 | 2 | 3, string> = {
    1: 'Como te chamam?',
    2: 'Quando você nasceu?',
    3: 'Escolha seu Pokémon',
};

const headline = computed(() => headlines[step.value]);

const selectedAvatar = computed(
    () =>
        props.avatarOptions.find(
            (option) => option.id === form.avatar_species_id,
        ) ?? null,
);

// Antes de escolher, o retrato de fundo mostra a primeira espécie da lista
// curada (config/game.php); depois que a pessoa escolhe, o fundo passa a
// refletir a escolha dela.
const backdropAvatar = computed(
    () => selectedAvatar.value ?? props.avatarOptions[0] ?? null,
);

// Prévia só visual do número depois do nickname — a tag de verdade é
// sorteada no servidor (com checagem de colisão contra pares
// nickname+tag já existentes), então pode não bater com essa aqui quando
// a conta for criada de fato.
const tagPreview = computed(() => {
    const nickname = form.nickname.trim();

    if (nickname.length < 2) {
        return null;
    }

    let hash = 0;

    for (const char of nickname) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return String(hash % 10000).padStart(4, '0');
});

function goToStep(target: 1 | 2 | 3) {
    step.value = target;
}

function submit() {
    form.post(store().url, {
        onError: (errors) => {
            if (errors.nickname) {
                step.value = 1;
            } else if (errors.birthdate) {
                step.value = 2;
            }
        },
    });
}
</script>

<template>
    <Head title="Complete seu perfil" />

    <div
        class="bg-base relative flex min-h-screen items-center justify-center overflow-hidden p-6"
    >
        <div
            class="pointer-events-none absolute inset-0 overflow-hidden"
            aria-hidden="true"
        >
            <img
                v-if="backdropAvatar"
                :key="backdropAvatar.id"
                :src="`/storage/${backdropAvatar.artwork_path}`"
                alt=""
                class="absolute inset-0 h-full w-full scale-125 object-cover object-center opacity-30 blur-3xl saturate-150 transition-opacity duration-700 dark:opacity-60"
            />
            <div class="bg-base/85 absolute inset-0" />
        </div>

        <div class="absolute top-6 right-6">
            <ThemeToggle />
        </div>

        <div class="relative z-10 flex w-full max-w-sm flex-col items-center gap-5">
            <div
                class="bg-surface/90 ring-ink/10 flex min-h-[52px] w-full items-center gap-3 rounded-full px-3 py-1.5 ring-1 backdrop-blur-xl"
            >
                <div
                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-[inset_0_0_0_1px_rgb(0_0_0/8%)]"
                >
                    <img
                        v-if="selectedAvatar"
                        :src="`/storage/${selectedAvatar.artwork_path}`"
                        :alt="selectedAvatar.name"
                        class="h-full w-full object-contain p-1"
                    />
                    <svg
                        v-else
                        viewBox="0 0 24 24"
                        fill="none"
                        class="text-ink/35 h-5 w-5"
                        aria-hidden="true"
                    >
                        <circle
                            cx="12"
                            cy="12"
                            r="8.5"
                            stroke="currentColor"
                            stroke-width="1.75"
                        />
                        <path
                            d="M3.5 12h17"
                            stroke="currentColor"
                            stroke-width="1.75"
                        />
                        <circle cx="12" cy="12" r="2.5" fill="currentColor" />
                    </svg>
                </div>
                <p class="text-ink/70 truncate text-sm font-semibold">
                    <template v-if="tagPreview">
                        <span class="text-ink">{{ form.nickname.trim() }}</span>
                        <span class="text-ink/40 font-medium"
                            >#{{ tagPreview }}</span
                        >
                    </template>
                    <template v-else>Seu cartão de treinador</template>
                </p>
            </div>

            <div class="flex gap-2">
                <div
                    v-for="n in 3"
                    :key="n"
                    class="bg-ink/10 h-1.5 w-9 overflow-hidden rounded-full"
                >
                    <div
                        class="bg-holo h-full rounded-full transition-all duration-500"
                        :style="{ width: n <= step ? '100%' : '0%' }"
                    />
                </div>
            </div>

            <div
                class="bg-surface ring-ink/10 relative w-full rounded-3xl p-8 shadow-xl ring-1 backdrop-blur-xl ring-inset"
            >
                <h1
                    class="font-display text-ink text-center text-[28px] leading-tight font-extrabold text-balance"
                >
                    {{ headline }}
                </h1>

                <form
                    v-if="step === 1"
                    class="mt-6 flex flex-col gap-4"
                    @submit.prevent="goToStep(2)"
                >
                    <AppInput
                        v-model="form.nickname"
                        label="Nickname"
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
                    class="mt-6 flex flex-col gap-4"
                    @submit.prevent="goToStep(3)"
                >
                    <AppInput
                        v-model="form.birthdate"
                        label="Data de nascimento"
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
                    class="mt-6 flex flex-col gap-4"
                    @submit.prevent="submit"
                >
                    <p class="text-ink/60 -mt-2 text-center text-sm">
                        Vai te representar pra galera do grupo.
                    </p>

                    <div class="grid grid-cols-3 gap-3">
                        <button
                            v-for="option in avatarOptions"
                            :key="option.id"
                            type="button"
                            class="avatar-tile"
                            :class="{
                                'avatar-tile--selected':
                                    form.avatar_species_id === option.id,
                            }"
                            :aria-label="option.name"
                            :aria-pressed="form.avatar_species_id === option.id"
                            @click="form.avatar_species_id = option.id"
                        >
                            <img
                                :src="`/storage/${option.artwork_path}`"
                                :alt="option.name"
                            />
                        </button>
                    </div>

                    <span
                        v-if="form.errors.avatar_species_id"
                        class="text-center text-xs font-semibold text-red-500"
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
                                form.avatar_species_id === null ||
                                form.processing
                            "
                            class="w-full"
                        >
                            {{ form.processing ? 'Salvando...' : 'Entrar no jogo' }}
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.avatar-tile {
    aspect-ratio: 1;
    border-radius: 9999px;
    background: #fff;
    padding: 10px;
    cursor: pointer;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 0 0 1px rgb(0 0 0 / 8%);
    transition: transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.avatar-tile img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.avatar-tile::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 9999px;
    background-image: var(--holo-gradient);
    opacity: 0;
    transition: opacity 220ms;
    z-index: -1;
}

.avatar-tile--selected {
    transform: scale(1.08);
}

.avatar-tile--selected::before {
    opacity: 1;
    animation: avatar-holo-spin 4s linear infinite;
}

@keyframes avatar-holo-spin {
    to {
        filter: hue-rotate(360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .avatar-tile--selected::before {
        animation: none;
    }

    .avatar-tile {
        transition: none;
    }
}
</style>
