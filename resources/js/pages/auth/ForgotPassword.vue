<script setup lang="ts">
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Auth/ForgotPasswordController';
import BrandLogo from '@/components/entry/BrandLogo.vue';
import EntryField from '@/components/entry/EntryField.vue';
import PrimaryButton from '@/components/entry/PrimaryButton.vue';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { enterCard } from '@/lib/cardEntrance';
import { animate, shake, spring } from '@/lib/motion';
import { login } from '@/routes';

defineOptions({ layout: EntryLayout });

/**
 * "Esqueci a senha": pede o email e manda o link. O servidor responde igual
 * tenha conta ou não, então a tela sempre termina em "confira seu email".
 */
defineProps<{ linkMinutes: number }>();

const EMAIL_PATTERN = /.+@.+\..+/;

const field = useDotField();
const card = ref<HTMLFormElement | null>(null);
const logo = ref<HTMLElement | null>(null);
const emailField = ref<InstanceType<typeof EntryField> | null>(null);
const sentPanel = ref<HTMLElement | null>(null);

const http = useHttp({ email: '' });
const isBusy = ref(false);
const isSent = ref(false);
const clientError = ref<string | null>(null);

onMounted(async () => {
    const { played } = await waitForIntro(logo.value);
    field.ambient();
    enterCard(card.value, logo.value, played ? 740 : 0, !played);
    window.setTimeout(
        () => emailField.value?.input?.focus({ preventScroll: true }),
        900,
    );
});

function fail(message: string) {
    clientError.value = message;
    shake(card.value);
    emailField.value?.input?.focus({ preventScroll: true });
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

    if (!EMAIL_PATTERN.test(http.email)) {
        fail('Esse email não parece certo');

        return;
    }

    isBusy.value = true;
    let succeeded = true;

    try {
        await http.post(store().url);
    } catch {
        succeeded = false;
    }

    isBusy.value = false;

    if (!succeeded || Object.keys(http.errors).length > 0) {
        fail(http.errors.email ?? 'Não deu pra mandar agora. Tenta de novo?');

        return;
    }

    isSent.value = true;
    await nextTick();
    animate(
        sentPanel.value,
        [
            { opacity: 0, transform: 'translateY(16px) scale(.96)' },
            { opacity: 1, transform: 'none' },
        ],
        600,
        { easing: spring(220, 18) },
    );
}
</script>

<template>
    <Head title="Esqueci a senha" />

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

            <template v-if="!isSent">
                <div
                    data-enter
                    class="flex flex-col items-center gap-1.5 text-center"
                >
                    <h1
                        class="text-ink text-[27px] leading-[1.1] font-black tracking-[-0.025em]"
                    >
                        Esqueceu a senha?
                    </h1>
                    <p
                        class="text-soft text-[14.5px] font-semibold text-balance"
                    >
                        Diga o email da conta e mandamos um link pra criar uma
                        senha nova
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
                        :error="clientError"
                        @update:model-value="clearErrors"
                    />
                </div>

                <div data-enter class="flex justify-center pt-1">
                    <PrimaryButton type="submit" size="lg" :dimmed="isBusy">
                        {{ isBusy ? 'Mandando…' : 'Mandar link' }}
                    </PrimaryButton>
                </div>
            </template>

            <div
                v-else
                ref="sentPanel"
                class="flex flex-col items-center gap-2 text-center"
                role="status"
            >
                <h1
                    class="text-ink text-[27px] leading-[1.1] font-black tracking-[-0.025em]"
                >
                    Confira seu email
                </h1>
                <p class="text-soft text-[14.5px] font-semibold text-balance">
                    Se <strong class="text-ink">{{ http.email }}</strong> tiver
                    conta no 512, o link chega em instantes. Ele vale por
                    {{ linkMinutes }} minutos. Olhe também o spam.
                </p>
            </div>

            <p
                data-enter
                class="text-soft text-center text-[13px] font-semibold"
            >
                Lembrou?
                <Link
                    :href="login()"
                    class="text-brand font-extrabold hover:underline"
                >
                    Voltar pro login
                </Link>
            </p>
        </form>
    </div>
</template>
