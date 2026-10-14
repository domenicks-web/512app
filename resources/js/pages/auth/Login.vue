<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Auth/LoginUserController';
import BrandLogo from '@/components/entry/BrandLogo.vue';
import EntryField from '@/components/entry/EntryField.vue';
import PasswordField from '@/components/entry/PasswordField.vue';
import PrimaryButton from '@/components/entry/PrimaryButton.vue';
import { useCaptureSubmit } from '@/composables/useCaptureSubmit';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { enterCard } from '@/lib/cardEntrance';
import { shake } from '@/lib/motion';
import { home, register } from '@/routes';
import { request as passwordRequest } from '@/routes/password';

defineOptions({ layout: EntryLayout });

/** Mesma duração do voo do logo no `EntryLayout`. */
const FLIGHT_DURATION = 780;
const EMAIL_PATTERN = /.+@.+\..+/;

const field = useDotField();
const { capture } = useCaptureSubmit();

const card = ref<HTMLFormElement | null>(null);
const logo = ref<HTMLElement | null>(null);
const button = ref<InstanceType<typeof PrimaryButton> | null>(null);
const emailField = ref<InstanceType<typeof EntryField> | null>(null);
const passwordField = ref<InstanceType<typeof PasswordField> | null>(null);

const isBusy = ref(false);
const clientError = ref<{
    field: 'email' | 'password';
    message: string;
} | null>(null);

/** O popup da senha nova manda pra cá com `?email=` pra já vir preenchido. */
const http = useHttp({ email: '', password: '', remember: false });

onMounted(async () => {
    // Com a abertura tocando, o logo dela voa até este logo e o card abre
    // junto; sem abertura (visita do Inertia), o logo só dá um pop.
    const prefilledEmail = new URLSearchParams(window.location.search).get(
        'email',
    );

    if (prefilledEmail) {
        http.email = prefilledEmail;
    }

    const { played } = await waitForIntro(logo.value);
    field.ambient();
    enterCard(
        card.value,
        logo.value,
        played ? FLIGHT_DURATION - 40 : 0,
        !played,
    );
});

function fail(target: 'email' | 'password', message: string) {
    clientError.value = { field: target, message };
    shake(card.value);
    const input =
        target === 'email'
            ? emailField.value?.input
            : passwordField.value?.input;
    input?.focus({ preventScroll: true });
}

function clearErrors() {
    clientError.value = null;
    http.clearErrors();
}

function errorFor(target: 'email' | 'password'): string | null {
    if (clientError.value?.field === target) {
        return clientError.value.message;
    }

    return http.errors[target] ?? null;
}

async function submit() {
    if (isBusy.value) {
        return;
    }

    clientError.value = null;
    http.clearErrors();

    if (!EMAIL_PATTERN.test(http.email)) {
        fail('email', 'Esse email não parece certo');

        return;
    }

    if (!http.password) {
        fail('password', 'Falta a senha');

        return;
    }

    if (!button.value?.root || !card.value) {
        return;
    }

    isBusy.value = true;

    const succeeded = await capture(button.value.root, card.value, async () => {
        try {
            await http.post(store().url);
        } catch {
            return false;
        }

        return Object.keys(http.errors).length === 0;
    });

    if (succeeded) {
        router.visit(home().url);

        return;
    }

    isBusy.value = false;
    const target =
        http.errors.password && !http.errors.email ? 'password' : 'email';
    fail(
        target,
        http.errors[target] ?? 'Não deu pra entrar agora. Tenta de novo?',
    );
}
</script>

<template>
    <Head title="Entrar" />

    <div
        class="absolute inset-0 z-[3] flex items-center justify-center overflow-y-auto p-4"
    >
        <form
            ref="card"
            novalidate
            class="flex w-full max-w-[400px] flex-col gap-[22px] rounded-[28px] bg-white px-[30px] pt-[34px] pb-[30px] shadow-[0_2px_4px_rgba(43,47,54,0.05),0_22px_56px_rgba(43,47,54,0.18)]"
            @submit.prevent="submit"
        >
            <div class="flex justify-center">
                <div ref="logo" class="[zoom:0.5]">
                    <BrandLogo />
                </div>
            </div>

            <div
                data-enter
                class="flex flex-col items-center gap-1.5 text-center"
            >
                <h1
                    class="text-ink text-[27px] leading-[1.1] font-black tracking-[-0.025em]"
                >
                    Bem-vindo de volta
                </h1>
                <p class="text-soft text-[14.5px] font-semibold">
                    Entre com sua conta pra continuar
                </p>
            </div>

            <div data-enter>
                <EntryField
                    ref="emailField"
                    v-model="http.email"
                    label="Email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    :error="errorFor('email')"
                    @update:model-value="clearErrors"
                />
            </div>

            <div data-enter>
                <PasswordField
                    ref="passwordField"
                    v-model="http.password"
                    label="Senha"
                    name="password"
                    :error="errorFor('password')"
                    @update:model-value="clearErrors"
                />
            </div>

            <div
                data-enter
                class="-mt-1.5 flex items-center justify-between gap-3"
            >
                <label
                    class="text-soft flex cursor-pointer items-center gap-2 text-[13px] font-bold select-none"
                >
                    <span
                        class="relative inline-flex h-[22px] w-[38px] shrink-0 items-center rounded-full transition-colors has-[:focus-visible]:shadow-[0_0_0_3px_rgba(242,77,77,0.35)]"
                        :class="http.remember ? 'bg-brand' : 'bg-[#dfe2e6]'"
                    >
                        <input
                            v-model="http.remember"
                            type="checkbox"
                            name="remember"
                            class="absolute inset-0 size-full cursor-pointer opacity-0"
                        />
                        <span
                            class="pointer-events-none block size-[16px] rounded-full bg-white shadow-[0_1px_3px_rgba(28,32,38,0.25)] transition-transform"
                            :class="
                                http.remember
                                    ? 'translate-x-[19px]'
                                    : 'translate-x-[3px]'
                            "
                        />
                    </span>
                    Manter conectado
                </label>
                <Link
                    :href="passwordRequest()"
                    class="text-brand text-[13px] font-extrabold hover:underline"
                >
                    Esqueci a senha
                </Link>
            </div>

            <div data-enter class="flex justify-center pt-1">
                <PrimaryButton ref="button" type="submit" size="lg">
                    Entrar
                </PrimaryButton>
            </div>

            <p
                data-enter
                class="text-soft text-center text-[13px] font-semibold"
            >
                Tem um convite?
                <Link
                    :href="register()"
                    class="text-brand font-extrabold hover:underline"
                >
                    Criar conta
                </Link>
            </p>
        </form>
    </div>
</template>
