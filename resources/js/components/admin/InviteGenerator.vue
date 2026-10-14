<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InviteSuffixController from '@/actions/App/Http/Controllers/Admin/InviteSuffixController';
import { store } from '@/routes/admin/invites';
import AdminIcon from './AdminIcon.vue';
import { copyText } from './clipboard';
import { showToast } from './toast';
import type { InviteForm } from './types';

/**
 * Card "Gerar convite". O sufixo vem do servidor (que guarda na sessão e
 * grava esse mesmo no POST). Enquanto ele não trava, os tiles giram
 * passando pelo alfabeto em ordem: é só efeito, nada é sorteado aqui.
 */

const props = defineProps<{ form: InviteForm }>();
const emit = defineEmits<{ created: [] }>();

const validityLabels: Record<string, string> = {
    '7': '7 dias',
    '30': '30 dias',
    never: 'Sem validade',
};

const prefix = ref(props.form.defaultPrefix);
const uses = ref(props.form.maxUses.min);
const validity = ref(props.form.defaultValidity);

const suffix = ref<string | null>(null);
const lockedCount = ref(0);
const tick = ref(0);
const copied = ref(false);
const errors = ref<Record<string, string>>({});
const creating = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;

const suffixRequest = useHttp({});

const isRolling = computed(() => lockedCount.value < props.form.suffixLength);
const code = computed(() =>
    suffix.value
        ? `${prefix.value || props.form.defaultPrefix}-${suffix.value}`
        : '',
);

/** O caractere de cada tile do sufixo: travado mostra o do servidor, girando passa pelo alfabeto. */
const suffixTiles = computed(() =>
    Array.from({ length: props.form.suffixLength }, (_, index) => {
        if (suffix.value && index < lockedCount.value) {
            return { char: suffix.value[index], locked: true };
        }

        const alphabet = props.form.alphabet;

        return {
            char: alphabet[(tick.value * 7 + index * 11) % alphabet.length],
            locked: false,
        };
    }),
);

/** 45ms por troca; trava um caractere a cada 4 trocas (≈720ms no total). */
function startRolling() {
    clearInterval(timer);
    lockedCount.value = 0;
    tick.value = 0;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        timer = setInterval(() => {
            if (suffix.value) {
                lockedCount.value = props.form.suffixLength;
                clearInterval(timer);
            }
        }, 45);

        return;
    }

    timer = setInterval(() => {
        tick.value++;

        if (suffix.value && tick.value % 4 === 0) {
            lockedCount.value++;
        }

        if (lockedCount.value >= props.form.suffixLength) {
            clearInterval(timer);
        }
    }, 45);
}

async function reroll() {
    suffix.value = null;
    startRolling();

    try {
        const response = (await suffixRequest.get(
            InviteSuffixController.url(),
        )) as { suffix: string };
        suffix.value = response.suffix;
    } catch {
        clearInterval(timer);
        showToast('Não deu pra sortear o código. Tente de novo.');
    }
}

function onPrefixInput(event: Event) {
    const input = event.target as HTMLInputElement;
    prefix.value = input.value
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, props.form.prefixMaxLength);
    input.value = prefix.value;
}

async function copy() {
    if (!code.value || isRolling.value) {
        return;
    }

    if (await copyText(code.value)) {
        copied.value = true;
        showToast(`${code.value} copiado`);
        setTimeout(() => (copied.value = false), 1400);
    }
}

function create() {
    router.post(
        store.url(),
        {
            prefix: prefix.value || props.form.defaultPrefix,
            max_uses: uses.value,
            validity: validity.value,
        },
        {
            preserveScroll: true,
            onStart: () => (creating.value = true),
            onFinish: () => (creating.value = false),
            onError: (formErrors) => (errors.value = formErrors),
            onSuccess: () => emit('created'),
        },
    );
}

onMounted(reroll);
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section
        class="invite-generator flex flex-wrap gap-7 rounded-[28px] bg-white p-5 shadow-[0_2px_3px_rgba(28,32,38,0.04),0_12px_32px_rgba(28,32,38,0.07)] sm:p-[26px]"
        aria-label="Gerar convite"
    >
        <div
            class="flex min-h-[200px] min-w-[min(100%,300px)] flex-[1.2] flex-col items-center justify-center gap-4 rounded-[20px] bg-[#1c1f26] bg-[radial-gradient(70%_45%_at_50%_46%,rgba(242,77,77,0.16),transparent)] px-4 py-8"
        >
            <span
                class="text-[11px] font-extrabold tracking-[0.18em] text-[#7d8794] uppercase"
                >Novo código</span
            >
            <div
                class="flex flex-wrap justify-center gap-[3px] font-mono text-[30px] font-extrabold"
                aria-live="polite"
                :aria-label="isRolling ? 'Sorteando o código' : code"
            >
                <span
                    v-for="(char, index) in prefix || form.defaultPrefix"
                    :key="`p${index}`"
                    class="flex h-[50px] min-w-[26px] items-center justify-center rounded-lg text-white"
                    aria-hidden="true"
                    >{{ char }}</span
                >
                <span
                    class="flex h-[50px] min-w-[26px] items-center justify-center text-[#7d8794]"
                    aria-hidden="true"
                    >-</span
                >
                <span
                    v-for="(tile, index) in suffixTiles"
                    :key="`s${index}`"
                    class="flex h-[50px] min-w-[34px] items-center justify-center rounded-lg transition-colors duration-150"
                    :class="
                        tile.locked
                            ? 'bg-[rgba(242,77,77,0.18)] text-[#ff8a8a]'
                            : 'bg-[rgba(255,255,255,0.06)] text-[#7d8794]'
                    "
                    aria-hidden="true"
                    >{{ tile.char }}</span
                >
            </div>
            <button
                type="button"
                class="flex h-[34px] cursor-pointer items-center gap-1.5 rounded-full bg-[rgba(255,255,255,0.08)] px-3.5 text-[13px] font-extrabold text-[#c3cad4] transition-colors hover:bg-[rgba(255,255,255,0.14)] disabled:cursor-default disabled:opacity-60"
                :disabled="isRolling"
                @click="reroll"
            >
                <AdminIcon name="reroll" :size="13" />
                Sortear outro
            </button>
        </div>

        <div class="flex min-w-[min(100%,300px)] flex-1 flex-col gap-4">
            <label class="flex flex-col gap-2">
                <span class="text-muted text-[13px] font-extrabold"
                    >Prefixo</span
                >
                <input
                    :value="prefix"
                    type="text"
                    inputmode="text"
                    autocapitalize="characters"
                    autocomplete="off"
                    spellcheck="false"
                    :maxlength="form.prefixMaxLength"
                    class="bg-field text-ink h-12 rounded-full px-5 font-mono text-base font-extrabold uppercase outline-none focus:bg-white focus:shadow-[inset_0_0_0_2px_#f24d4d]"
                    @input="onPrefixInput"
                />
                <span
                    v-if="errors.prefix"
                    class="text-brand-ink text-[13px] font-bold"
                    >{{ errors.prefix }}</span
                >
            </label>

            <div class="flex flex-wrap gap-4">
                <div class="flex flex-col gap-2">
                    <span
                        id="uses-label"
                        class="text-muted text-[13px] font-extrabold"
                        >Usos</span
                    >
                    <div
                        class="bg-field flex h-12 items-center gap-1 rounded-full p-1"
                        role="group"
                        aria-labelledby="uses-label"
                    >
                        <button
                            type="button"
                            class="text-ink flex size-10 cursor-pointer items-center justify-center rounded-full bg-white text-lg font-black shadow-[0_1px_3px_rgba(28,32,38,0.1)] disabled:cursor-default disabled:opacity-40"
                            :disabled="uses <= form.maxUses.min"
                            aria-label="Menos usos"
                            @click="uses--"
                        >
                            −
                        </button>
                        <span
                            class="w-9 text-center text-lg font-black"
                            aria-live="polite"
                            >{{ uses }}</span
                        >
                        <button
                            type="button"
                            class="text-ink flex size-10 cursor-pointer items-center justify-center rounded-full bg-white text-lg font-black shadow-[0_1px_3px_rgba(28,32,38,0.1)] disabled:cursor-default disabled:opacity-40"
                            :disabled="uses >= form.maxUses.max"
                            aria-label="Mais usos"
                            @click="uses++"
                        >
                            +
                        </button>
                    </div>
                </div>

                <div class="flex min-w-[260px] flex-1 flex-col gap-2">
                    <span
                        id="validity-label"
                        class="text-muted text-[13px] font-extrabold"
                        >Validade</span
                    >
                    <div
                        class="bg-field grid h-12 grid-cols-3 rounded-full p-1"
                        role="radiogroup"
                        aria-labelledby="validity-label"
                    >
                        <button
                            v-for="option in form.validities"
                            :key="option"
                            type="button"
                            role="radio"
                            :aria-checked="validity === option"
                            class="cursor-pointer rounded-full px-2 text-[13.5px] font-extrabold whitespace-nowrap transition-colors"
                            :class="
                                validity === option
                                    ? 'text-ink bg-white shadow-[0_1px_3px_rgba(28,32,38,0.1)]'
                                    : 'text-soft'
                            "
                            @click="validity = option"
                        >
                            {{ validityLabels[option] ?? option }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-auto flex gap-3 pt-1">
                <button
                    type="button"
                    class="text-ink h-[52px] flex-1 cursor-pointer rounded-full bg-white text-[15px] font-extrabold shadow-[inset_0_0_0_2px_#e4e6ea] transition-transform active:scale-[0.97] disabled:cursor-default disabled:opacity-50"
                    :disabled="isRolling"
                    @click="copy"
                >
                    {{ copied ? 'Copiado' : 'Copiar' }}
                </button>
                <button
                    type="button"
                    class="bg-brand h-[52px] flex-[1.4] cursor-pointer rounded-full text-[15px] font-extrabold text-white shadow-[0_10px_22px_rgba(242,77,77,0.42)] transition-transform active:scale-[0.97] disabled:cursor-default disabled:bg-[#f5a3a3] disabled:shadow-none"
                    :disabled="isRolling || creating"
                    @click="create"
                >
                    Criar convite
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.invite-generator {
    animation: pop-in 0.32s cubic-bezier(0.2, 1.4, 0.4, 1);
}

@keyframes pop-in {
    from {
        opacity: 0;
        transform: scale(0.96) translateY(-6px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .invite-generator {
        animation: none;
    }
}
</style>
