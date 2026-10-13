<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Auth/RegisterUserController';
import {
    destroy as forgetInvite,
    store as validateInvite,
} from '@/actions/App/Http/Controllers/Auth/ValidateInviteController';
import BrandLogo from '@/components/entry/BrandLogo.vue';
import EntryField from '@/components/entry/EntryField.vue';
import PasswordField from '@/components/entry/PasswordField.vue';
import PrimaryButton from '@/components/entry/PrimaryButton.vue';
import StepPills from '@/components/entry/StepPills.vue';
import { useCaptureSubmit } from '@/composables/useCaptureSubmit';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import { usePillMorph } from '@/composables/usePillMorph';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { shake } from '@/lib/motion';
import { home, login } from '@/routes';

defineOptions({ layout: EntryLayout });

/**
 * Cadastro em dois passos com a mecânica de pill do handoff: primeiro o
 * convite (conferido no servidor, que guarda na sessão), depois email e
 * senha. `inviteCode` vem preenchido quando a sessão já tem um convite
 * válido — aí a página já abre no segundo passo.
 */
const props = defineProps<{
    inviteCode: string | null;
}>();

const EMAIL_PATTERN = /.+@.+\..+/;

const field = useDotField();
const { capture } = useCaptureSubmit();

const card = ref<HTMLElement | null>(null);
const content = ref<HTMLElement | null>(null);
const pills = ref<InstanceType<typeof StepPills> | null>(null);
const pillsRoot = ref<HTMLElement | null>(null);
const button = ref<InstanceType<typeof PrimaryButton> | null>(null);
const emailField = ref<InstanceType<typeof EntryField> | null>(null);
const passwordField = ref<InstanceType<typeof PasswordField> | null>(null);
const inviteField = ref<InstanceType<typeof EntryField> | null>(null);

const morph = usePillMorph(card, content, pillsRoot);

const step = ref(props.inviteCode ? 1 : 0);
const validatedInvite = ref(props.inviteCode);
const isMoving = ref(false);
const clientError = ref<{ field: string; message: string } | null>(null);

const inviteHttp = useHttp({ invite_code: '' });
const forgetHttp = useHttp({});
const accountHttp = useHttp({
    email: '',
    password: '',
    password_confirmation: '',
});

onMounted(async () => {
    pillsRoot.value = pills.value?.root ?? null;
    await waitForIntro();

    if (field.mode() !== 'ambient') {
        field.ambient();
    }

    await nextTick();
    morph.enterPills();
    morph.morphFromPill(step.value, 300);
    window.setTimeout(() => focusFirstField(), 900);
});

function focusFirstField() {
    const input =
        step.value === 0 ? inviteField.value?.input : emailField.value?.input;
    input?.focus({ preventScroll: true });
}

async function goToStep(target: number) {
    if (isMoving.value) {
        return;
    }

    isMoving.value = true;
    await morph.goStep(step.value, target, async () => {
        step.value = target;
        await nextTick();
    });
    isMoving.value = false;
    window.setTimeout(() => focusFirstField(), 500);
}

function fail(
    target: string,
    message: string,
    input?: HTMLInputElement | null,
) {
    clientError.value = { field: target, message };
    shake(card.value);
    input?.focus({ preventScroll: true });
}

function clearErrors() {
    clientError.value = null;
    inviteHttp.clearErrors();
    accountHttp.clearErrors();
}

function errorFor(target: string): string | null {
    if (clientError.value?.field === target) {
        return clientError.value.message;
    }

    const errors: Record<string, string | undefined> = {
        ...inviteHttp.errors,
        ...accountHttp.errors,
    };

    return errors[target] ?? null;
}

async function submitInvite() {
    if (isMoving.value || inviteHttp.processing) {
        return;
    }

    clearErrors();

    if (!inviteHttp.invite_code.trim()) {
        fail(
            'invite_code',
            'Digite o código do seu convite.',
            inviteField.value?.input,
        );

        return;
    }

    try {
        const response = (await inviteHttp.post(validateInvite().url)) as {
            inviteCode: string;
        } | null;

        if (!response) {
            shake(card.value);

            return;
        }

        validatedInvite.value = response.inviteCode;
        await goToStep(1);
    } catch {
        fail(
            'invite_code',
            'Não deu pra conferir o convite agora. Tenta de novo?',
        );
    }
}

async function changeInvite() {
    if (isMoving.value) {
        return;
    }

    try {
        await forgetHttp.delete(forgetInvite().url);
    } catch {
        // Mesmo se falhar, o servidor reconfere o convite no cadastro.
    }

    validatedInvite.value = null;
    inviteHttp.invite_code = '';
    clearErrors();
    await goToStep(0);
}

async function submitAccount() {
    if (accountHttp.processing || isMoving.value) {
        return;
    }

    clearErrors();

    if (!EMAIL_PATTERN.test(accountHttp.email)) {
        fail('email', 'Esse email não parece certo', emailField.value?.input);

        return;
    }

    if (!accountHttp.password) {
        fail('password', 'Falta a senha', passwordField.value?.input);

        return;
    }

    if (accountHttp.password !== accountHttp.password_confirmation) {
        fail('password_confirmation', 'As senhas não batem');

        return;
    }

    if (!button.value?.root || !card.value) {
        return;
    }

    const succeeded = await capture(button.value.root, card.value, async () => {
        try {
            await accountHttp.post(store().url);
        } catch {
            return false;
        }

        return Object.keys(accountHttp.errors).length === 0;
    });

    if (succeeded) {
        router.visit(home().url);

        return;
    }

    // Convite revogado/esgotado entre a validação e o cadastro: o servidor
    // já esqueceu ele da sessão, então volta pro primeiro passo.
    const inviteError = (
        accountHttp.errors as Record<string, string | undefined>
    ).invite_code;

    if (inviteError) {
        const message = inviteError;
        validatedInvite.value = null;
        await goToStep(0);
        clientError.value = { field: 'invite_code', message };

        return;
    }

    shake(card.value);
}
</script>

<template>
    <Head title="Cadastro" />

    <div
        class="justify-[safe_center] absolute inset-0 z-[3] flex flex-col items-center gap-[18px] overflow-y-auto px-4 py-6"
    >
        <StepPills ref="pills" :count="2" :current="step" />

        <div
            ref="card"
            class="w-full max-w-[400px] rounded-[28px] bg-white px-[30px] pt-[34px] pb-[30px] shadow-[0_2px_4px_rgba(43,47,54,0.05),0_22px_56px_rgba(43,47,54,0.18)]"
        >
            <div ref="content" class="flex flex-col gap-[22px]">
                <div data-enter class="flex justify-center">
                    <div class="[zoom:0.5]">
                        <BrandLogo />
                    </div>
                </div>

                <form
                    v-if="step === 0"
                    novalidate
                    class="flex flex-col gap-[22px]"
                    @submit.prevent="submitInvite"
                >
                    <div
                        data-enter
                        class="flex flex-col items-center gap-1.5 text-center"
                    >
                        <h1
                            class="text-ink text-[27px] leading-[1.15] font-black tracking-[-0.025em] text-balance"
                        >
                            Aqui é só por convite
                        </h1>
                        <p
                            class="text-soft text-[14px] leading-[1.45] font-semibold text-pretty"
                        >
                            O 512 é um jogo privado entre amigos. Digite o
                            código que alguém que já joga te mandou.
                        </p>
                    </div>

                    <div data-enter>
                        <EntryField
                            ref="inviteField"
                            v-model="inviteHttp.invite_code"
                            label="Código de convite"
                            name="invite_code"
                            :error="errorFor('invite_code')"
                            @update:model-value="clearErrors"
                        />
                    </div>

                    <div data-enter class="pt-1">
                        <PrimaryButton
                            type="submit"
                            :dimmed="!inviteHttp.invite_code.trim()"
                        >
                            {{
                                inviteHttp.processing
                                    ? 'Conferindo…'
                                    : 'Continuar'
                            }}
                        </PrimaryButton>
                    </div>
                </form>

                <form
                    v-else
                    novalidate
                    class="flex flex-col gap-[22px]"
                    @submit.prevent="submitAccount"
                >
                    <div
                        data-enter
                        class="flex flex-col items-center gap-2 text-center"
                    >
                        <h1
                            class="text-ink text-[27px] leading-[1.15] font-black tracking-[-0.025em] text-balance"
                        >
                            Cria sua conta
                        </h1>
                        <p
                            class="text-soft flex items-center gap-2 text-[14px] font-semibold"
                        >
                            Convite
                            <span
                                class="bg-field text-ink rounded-full px-3 py-0.5 text-[12.5px] font-black tracking-wide"
                            >
                                {{ validatedInvite }}
                            </span>
                            <button
                                type="button"
                                class="text-brand cursor-pointer text-[12.5px] font-extrabold hover:underline"
                                @click="changeInvite"
                            >
                                trocar
                            </button>
                        </p>
                    </div>

                    <div data-enter>
                        <EntryField
                            ref="emailField"
                            v-model="accountHttp.email"
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
                            v-model="accountHttp.password"
                            label="Senha"
                            name="password"
                            autocomplete="new-password"
                            :error="errorFor('password')"
                            @update:model-value="clearErrors"
                        />
                    </div>

                    <div data-enter>
                        <PasswordField
                            v-model="accountHttp.password_confirmation"
                            label="Confirme a senha"
                            name="password_confirmation"
                            autocomplete="new-password"
                            :error="errorFor('password_confirmation')"
                            @update:model-value="clearErrors"
                        />
                    </div>

                    <div data-enter class="flex justify-center pt-1">
                        <PrimaryButton ref="button" type="submit" size="lg">
                            Criar conta
                        </PrimaryButton>
                    </div>
                </form>

                <p
                    data-enter
                    class="text-soft text-center text-[13px] font-semibold"
                >
                    Já tem conta?
                    <Link
                        :href="login()"
                        class="text-brand font-extrabold hover:underline"
                    >
                        Entrar
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>
