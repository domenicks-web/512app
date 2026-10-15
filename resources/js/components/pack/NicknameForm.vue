<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { nickname as nicknameRoute } from '@/routes/specimens';

/**
 * "Dar apelido" na ficha clara: o campo e, antes de gravar, a confirmação
 * de que é definitivo (um apelido por dono; na troca ele vai pro
 * histórico). Quem decide se pode é o servidor (`canName`); aqui só se
 * mostra o erro que ele devolver.
 */
const props = defineProps<{
    specimenId: number;
    speciesName: string;
    maxLength: number;
}>();
const emit = defineEmits<{ named: [nickname: string] }>();

const stage = ref<'idle' | 'typing' | 'confirming'>('idle');
const value = ref('');
const error = ref<string | null>(null);
const isSaving = ref(false);
const input = ref<HTMLInputElement | null>(null);

async function start() {
    stage.value = 'typing';
    await nextTick();
    input.value?.focus();
}

function review() {
    value.value = value.value.trim();

    if (value.value === '') {
        error.value = 'Escolha um apelido.';

        return;
    }

    error.value = null;
    stage.value = 'confirming';
}

function save() {
    isSaving.value = true;
    router.post(
        nicknameRoute(props.specimenId).url,
        { nickname: value.value },
        {
            preserveState: true,
            preserveScroll: true,
            // A ficha é JSON à parte: a página não precisa recarregar nada.
            only: ['auth'],
            onSuccess: () => emit('named', value.value),
            onError: (errors) => {
                error.value = errors.nickname ?? 'Não deu pra salvar.';
                stage.value = 'typing';
            },
            onFinish: () => (isSaving.value = false),
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <button
            v-if="stage === 'idle'"
            type="button"
            class="flex h-9 cursor-pointer items-center gap-1.5 self-start rounded-full bg-[#f4f5f6] px-3.5 text-[13px] font-black text-[#2b2f36] transition-transform active:scale-95"
            @click="start"
        >
            <svg
                width="13"
                height="13"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.6"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4" />
            </svg>
            Dar apelido
        </button>

        <form
            v-else-if="stage === 'typing'"
            class="flex flex-col gap-2"
            @submit.prevent="review"
        >
            <div class="flex items-center gap-2">
                <div
                    class="flex h-11 min-w-0 flex-1 items-center rounded-full bg-[#f4f5f6] pr-3.5 pl-4 has-[:focus-visible]:shadow-[0_0_0_3px_rgba(242,77,77,0.35)]"
                >
                    <input
                        ref="input"
                        v-model="value"
                        type="text"
                        :maxlength="maxLength"
                        :placeholder="`Apelido do ${speciesName}`"
                        aria-label="Apelido"
                        autocomplete="off"
                        class="min-w-0 flex-1 bg-transparent text-[15px] font-black text-[#2b2f36] outline-none placeholder:font-semibold placeholder:text-[#9aa0a8]"
                    />
                    <span
                        class="text-[11px] font-extrabold text-[#9aa0a8] tabular-nums"
                        >{{ value.length }}/{{ maxLength }}</span
                    >
                </div>
                <button
                    type="submit"
                    class="h-11 shrink-0 cursor-pointer rounded-full bg-[#F24D4D] px-4 text-[14px] font-black text-white transition-transform active:scale-95"
                >
                    Continuar
                </button>
            </div>
            <button
                type="button"
                class="cursor-pointer self-start text-[12px] font-extrabold text-[#767c85]"
                @click="((stage = 'idle'), (error = null))"
            >
                Cancelar
            </button>
        </form>

        <div
            v-else
            class="flex flex-col gap-3 rounded-2xl bg-[#fff6e0] p-3.5"
            role="alertdialog"
            aria-label="Confirmar apelido"
        >
            <p class="text-[14px] leading-snug font-bold text-[#2b2f36]">
                {{ speciesName }} vai se chamar
                <span class="font-black">{{ value }}</span
                >. Não dá pra mudar depois.
            </p>
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="h-10 cursor-pointer rounded-full bg-white text-[14px] font-black text-[#2b2f36] shadow-[0_1px_3px_rgba(28,32,38,0.12)]"
                    :disabled="isSaving"
                    @click="stage = 'typing'"
                >
                    Voltar
                </button>
                <button
                    type="button"
                    class="h-10 cursor-pointer rounded-full bg-[#F24D4D] text-[14px] font-black text-white disabled:opacity-60"
                    :disabled="isSaving"
                    @click="save"
                >
                    {{ isSaving ? 'Salvando…' : 'Confirmar' }}
                </button>
            </div>
        </div>

        <span
            v-if="error"
            role="alert"
            class="self-start rounded-full bg-[rgba(242,77,77,0.12)] px-3 py-1.5 text-[12.5px] font-extrabold text-[#c43434]"
            >{{ error }}</span
        >
    </div>
</template>
