<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { redirect as googleRedirect } from '@/routes/auth/google';
</script>

<template>
    <Head title="Entrar" />

    <div
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#0b0b0f] p-6"
    >
        <div class="pointer-events-none absolute inset-0">
            <div
                class="absolute -top-32 -left-24 h-96 w-96 rounded-full bg-red-600/40 blur-[120px]"
            ></div>
            <div
                class="absolute top-1/3 -right-24 h-96 w-96 rounded-full bg-indigo-600/30 blur-[120px]"
            ></div>
            <div
                class="absolute -bottom-40 left-1/4 h-96 w-96 rounded-full bg-amber-400/20 blur-[130px]"
            ></div>
        </div>

        <div
            class="relative w-full max-w-sm rounded-[2rem] border border-white/10 bg-white/[0.06] p-8 shadow-2xl backdrop-blur-2xl"
        >
            <div class="flex flex-col items-center text-center">
                <span class="text-5xl font-extrabold tracking-tight text-white"
                    >512</span
                >
                <p class="mt-2 text-sm text-white/60">
                    Entre pra abrir seu pack de hoje
                </p>
            </div>

            <a
                :href="googleRedirect().url"
                class="mt-8 flex w-full items-center justify-center gap-3 rounded-full bg-white px-6 py-3 text-sm font-semibold text-[#1b1b18] shadow-lg transition hover:bg-white/90 active:scale-[0.98]"
            >
                <svg viewBox="0 0 48 48" class="h-5 w-5" aria-hidden="true">
                    <path
                        fill="#FFC107"
                        d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"
                    />
                    <path
                        fill="#FF3D00"
                        d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"
                    />
                    <path
                        fill="#4CAF50"
                        d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"
                    />
                    <path
                        fill="#1976D2"
                        d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"
                    />
                </svg>
                Entrar com Google
            </a>

            <p class="mt-6 text-center text-xs text-white/40">
                Jogo privado entre amigos. Só entra quem já foi convidado.
            </p>
        </div>
    </div>
</template>
