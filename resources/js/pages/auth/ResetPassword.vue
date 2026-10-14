<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Auth/ResetPasswordController';
import BrandLogo from '@/components/entry/BrandLogo.vue';
import PasswordField from '@/components/entry/PasswordField.vue';
import PrimaryButton from '@/components/entry/PrimaryButton.vue';
import ConfirmDialog from '@/components/group/ConfirmDialog.vue';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { enterCard } from '@/lib/cardEntrance';
import { shake } from '@/lib/motion';
import { login } from '@/routes';
import { request as passwordRequest } from '@/routes/password';

defineOptions({ layout: EntryLayout });

/**
 * Tela da senha nova, aberta pelo link do email. Só a senha e a
 * confirmação: email e token ficam na sessão do servidor. Depois de
 * trocar, o popup leva pro login com o email preenchido (o link não loga).
 */
const props = defineProps<{ isLinkValid: boolean }>();

const field = useDotField();
const card = ref<HTMLFormElement | null>(null);
const logo = ref<HTMLElement | null>(null);
const passwordField = ref<InstanceType<typeof PasswordField> | null>(null);
const confirmationField = ref<InstanceType<typeof PasswordField> | null>(null);

const http = useHttp({ password: '', password_confirmation: '' });
const isBusy = ref(false);
const isLinkExpired = ref(!props.isLinkValid);
const changedEmail = ref<string | null>(null);
const clientError = ref<{
    field: 'password' | 'password_confirmation';
    message: string;
} | null>(null);

onMounted(async () => {
    const { played } = await waitForIntro(logo.value);
    field.ambient();
    enterCard(card.value, logo.value, played ? 740 : 0, !played);
    window.setTimeout(
        () => passwordField.value?.input?.focus({ preventScroll: true }),
        900,
    );
});

function errorFor(target: 'password' | 'password_confirmation') {
    if (clientError.value?.field === target) {
        return clientError.value.message;
    }

    return http.errors[target] ?? null;
}

function fail(target: 'password' | 'password_confirmation', message: string) {
    clientError.value = { field: target, message };
    shake(card.value);
    const input =
        target === 'password'
            ? passwordField.value?.input
            : confirmationField.value?.input;
    input?.focus({ preventScroll: true });
}

function clearErrors() {
    clientError.value = null;
    http.clearErrors();
}

async function submit() {
    if (isBusy.value) {
        return;
    }

    clearErrors();

    if (!http.password) {
        fail('password', 'Falta a senha nova');

        return;
    }

    if (http.password !== http.password_confirmation) {
        fail('password_confirmation', 'As duas senhas não batem');

        return;
    }

    isBusy.value = true;
    let response: { email: string } | null = null;

    try {
        response = (await http.post(store().url)) as { email: string };
    } catch {
        response = null;
    }

    isBusy.value = false;

    if (response?.email) {
        changedEmail.value = response.email;

        return;
    }

    if ((http.errors as Record<string, string | undefined>).token) {
        isLinkExpired.value = true;

        return;
    }

    fail(
        http.errors.password_confirmation
            ? 'password_confirmation'
            : 'password',
        http.errors.password_confirmation ??
            http.errors.password ??
            'Não deu pra trocar agora. Tenta de novo?',
    );
}

function goToLogin() {
    router.visit(
        login.url({ query: { email: changedEmail.value ?? undefined } }),
    );
}
</script>

<template>
    <Head title="Senha nova" />

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

            <template v-if="isLinkExpired">
                <div
                    data-enter
                    class="flex flex-col items-center gap-1.5 text-center"
                >
                    <h1
                        class="text-ink text-[27px] leading-[1.1] font-black tracking-[-0.025em]"
                    >
                        Link expirado
                    </h1>
                    <p
                        class="text-soft text-[14.5px] font-semibold text-balance"
                    >
                        Esse link já foi usado ou passou da validade. Peça outro
                        e use o mais recente.
                    </p>
                </div>
                <div data-enter>
                    <Link
                        :href="passwordRequest()"
                        class="bg-brand flex h-[58px] w-full items-center justify-center rounded-full text-[17px] font-extrabold text-white shadow-[0_10px_24px_rgba(242,77,77,0.42)] transition-transform hover:-translate-y-0.5 active:scale-[0.97]"
                    >
                        Pedir outro link
                    </Link>
                </div>
            </template>

            <template v-else>
                <div
                    data-enter
                    class="flex flex-col items-center gap-1.5 text-center"
                >
                    <h1
                        class="text-ink text-[27px] leading-[1.1] font-black tracking-[-0.025em]"
                    >
                        Senha nova
                    </h1>
                    <p
                        class="text-soft text-[14.5px] font-semibold text-balance"
                    >
                        Escolha a senha nova da sua conta
                    </p>
                </div>

                <div data-enter>
                    <PasswordField
                        ref="passwordField"
                        v-model="http.password"
                        label="Senha nova"
                        name="password"
                        autocomplete="new-password"
                        :error="errorFor('password')"
                        @update:model-value="clearErrors"
                    />
                </div>

                <div data-enter>
                    <PasswordField
                        ref="confirmationField"
                        v-model="http.password_confirmation"
                        label="Repita a senha"
                        name="password_confirmation"
                        autocomplete="new-password"
                        :error="errorFor('password_confirmation')"
                        @update:model-value="clearErrors"
                    />
                </div>

                <div data-enter class="flex justify-center pt-1">
                    <PrimaryButton type="submit" size="lg" :dimmed="isBusy">
                        {{ isBusy ? 'Salvando…' : 'Trocar senha' }}
                    </PrimaryButton>
                </div>
            </template>
        </form>

        <ConfirmDialog
            :open="changedEmail !== null"
            title="Senha trocada"
            confirm-label="Ir pro login"
            :hide-cancel="true"
            @confirm="goToLogin"
            @cancel="goToLogin"
        >
            Pronto! Entre com a senha nova. Por segurança, todos os aparelhos
            que estavam conectados foram desconectados.
        </ConfirmDialog>
    </div>
</template>
