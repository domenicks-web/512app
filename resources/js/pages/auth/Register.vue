<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/Auth/RegisterUserController';
import { login } from '@/routes';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/AppInput.vue';
import BrandMark from '@/components/BrandMark.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
</script>

<template>
    <Head title="Cadastro" />

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
                    Cria sua conta
                </h1>
                <p class="text-ink/60 mt-2 text-sm">
                    Precisa de um código de convite de quem já joga
                </p>
            </div>

            <Form
                :action="store()"
                reset-on-error
                class="mt-8 flex flex-col gap-4"
                #default="{ errors, processing }"
            >
                <AppInput
                    label="Email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    :error="errors.email"
                />

                <AppInput
                    label="Senha"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                    :error="errors.password"
                />

                <AppInput
                    label="Confirme a senha"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />

                <AppInput
                    label="Código de convite"
                    name="invite_code"
                    autocomplete="off"
                    required
                    :error="errors.invite_code"
                />

                <AppButton
                    type="submit"
                    :disabled="processing"
                    class="mt-2 w-full"
                >
                    {{ processing ? 'Criando conta...' : 'Criar conta' }}
                </AppButton>
            </Form>

            <p class="text-ink/40 mt-6 text-center text-xs">
                Já tem conta?
                <Link
                    :href="login()"
                    class="text-brand cursor-pointer font-bold hover:underline"
                    >Entrar</Link
                >
            </p>
        </div>
    </div>
</template>
