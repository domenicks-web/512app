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
import { animate, shake, spring } from '@/lib/motion';
import { home, register } from '@/routes';

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

const http = useHttp({ email: '', password: '' });

/** O card se abre a partir do retângulo do logo; os filhos entram a cada 70ms. */
function enterCard(delay: number, popLogo: boolean) {
    if (!card.value || !logo.value) {
        return;
    }

    const cardRect = card.value.getBoundingClientRect();
    const logoRect = logo.value.getBoundingClientRect();
    const inset = `inset(${logoRect.top - cardRect.top}px ${cardRect.right - logoRect.right}px ${cardRect.bottom - logoRect.bottom}px ${logoRect.left - cardRect.left}px round 20px)`;

    animate(
        card.value,
        [
            { clipPath: inset, opacity: 0 },
            { opacity: 1, offset: 0.06 },
            { clipPath: 'inset(0px 0px 0px 0px round 28px)', opacity: 1 },
        ],
        780,
        { delay, easing: spring(170, 19) },
    );

    if (popLogo) {
        animate(
            logo.value,
            [{ transform: 'scale(0) rotate(-20deg)' }, { transform: 'none' }],
            700,
            {
                delay,
                easing: spring(280, 14),
            },
        );
    } else {
        animate(logo.value, [{ opacity: 0 }, { opacity: 1 }], 1, {
            delay: delay + 30,
        });
        animate(
            logo.value,
            [
                { transform: 'none' },
                { transform: 'scale(1.08,.9)', offset: 0.25 },
                { transform: 'scale(.97,1.03)', offset: 0.6 },
                { transform: 'none' },
            ],
            480,
            { delay: delay + 40, fill: 'none' },
        );
    }

    card.value.querySelectorAll('[data-enter]').forEach((element, index) =>
        animate(
            element,
            [
                { opacity: 0, transform: 'translateY(16px)' },
                { opacity: 1, transform: 'none' },
            ],
            700,
            {
                delay: delay + 200 + index * 70,
                easing: spring(200, 17),
            },
        ),
    );
}

onMounted(async () => {
    // Com a abertura tocando, o logo dela voa até este logo e o card abre
    // junto; sem abertura (visita do Inertia), o logo só dá um pop.
    const { played } = await waitForIntro(logo.value);
    field.ambient();
    enterCard(played ? FLIGHT_DURATION - 40 : 0, !played);
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
