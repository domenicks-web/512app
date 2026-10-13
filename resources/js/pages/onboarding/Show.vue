<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Onboarding/CompleteOnboardingController';
import PreviewUserTagController from '@/actions/App/Http/Controllers/Onboarding/PreviewUserTagController';
import BirthdateWheels from '@/components/entry/BirthdateWheels.vue';
import EntryField from '@/components/entry/EntryField.vue';
import PartnerGrid from '@/components/entry/PartnerGrid.vue';
import type { PartnerOption } from '@/components/entry/PartnerGrid.vue';
import PrimaryButton from '@/components/entry/PrimaryButton.vue';
import SecondaryButton from '@/components/entry/SecondaryButton.vue';
import StepPills from '@/components/entry/StepPills.vue';
import TrainerChip from '@/components/entry/TrainerChip.vue';
import { useDotField } from '@/composables/useDotField';
import { waitForIntro } from '@/composables/useIntro';
import { usePillMorph } from '@/composables/usePillMorph';
import EntryLayout from '@/layouts/EntryLayout.vue';
import { animate, shake, spring, wait } from '@/lib/motion';
import { home } from '@/routes';

defineOptions({ layout: EntryLayout });

/**
 * Wizard pós-login em 3 passos (nickname → nascimento → parceiro), com o
 * chip de treinador se preenchendo em cima e a mecânica de pills entre os
 * passos. A tag vem do servidor (prévia guardada na sessão) e o perfil só
 * é gravado no último passo.
 */
const props = defineProps<{
    avatarOptions: PartnerOption[];
    nicknameMaxLength: number;
    birthYearRange: [number, number];
}>();

const TAG_DEBOUNCE_MS = 400;
/** Centro do avatar da home (padding 28/22 + metade dos 48px). */
const HOME_AVATAR_CENTER = { x: 52, y: 46 };

const field = useDotField();

const chip = ref<InstanceType<typeof TrainerChip> | null>(null);
const pills = ref<InstanceType<typeof StepPills> | null>(null);
const pillsRoot = ref<HTMLElement | null>(null);
const card = ref<HTMLElement | null>(null);
const content = ref<HTMLElement | null>(null);
const nicknameField = ref<InstanceType<typeof EntryField> | null>(null);
const partnerGrid = ref<InstanceType<typeof PartnerGrid> | null>(null);

const morph = usePillMorph(card, content, pillsRoot);

const step = ref(0);
const isMoving = ref(false);
const tag = ref<string | null>(null);
const chipAvatarPath = ref<string | null>(null);
const nicknameError = ref<string | null>(null);

// Padrão inicial das rodas: 13 de setembro, 20 anos atrás do ano mais recente.
const day = ref(13);
const month = ref(8);
const year = ref(
    Math.max(props.birthYearRange[0], props.birthYearRange[1] - 20),
);

const profile = useHttp({
    nickname: '',
    birthdate: '',
    avatar_species_id: null as number | null,
});
const tagPreview = useHttp({ nickname: '' });

const hasNickname = computed(() => profile.nickname.trim().length > 0);
const birthdate = computed(
    () =>
        `${year.value}-${String(month.value + 1).padStart(2, '0')}-${String(day.value).padStart(2, '0')}`,
);

let tagTimer = 0;

function onNicknameInput() {
    nicknameError.value = null;
    profile.clearErrors();
    window.clearTimeout(tagTimer);
    const nickname = profile.nickname.trim();

    if (nickname.length < 2) {
        return;
    }

    tagTimer = window.setTimeout(async () => {
        tagPreview.nickname = nickname;

        try {
            const response = (await tagPreview.get(
                PreviewUserTagController.url(),
            )) as {
                tag: string;
            } | null;

            if (response && profile.nickname.trim() === nickname) {
                tag.value = response.tag;
            }
        } catch {
            // Sem prévia a tag fica como estava; o servidor sorteia no fim.
        }
    }, TAG_DEBOUNCE_MS);
}

onMounted(async () => {
    pillsRoot.value = pills.value?.root ?? null;
    await waitForIntro();

    if (field.mode() !== 'ambient') {
        field.ambient();
    }

    await nextTick();
    animate(
        chip.value?.root,
        [
            { transform: 'translateY(-60px) scale(.6)', opacity: 0 },
            { opacity: 1, offset: 0.1 },
            { transform: 'none', opacity: 1 },
        ],
        760,
        { easing: spring(220, 15) },
    );
    morph.enterPills();
    morph.morphFromPill(0, 300);
    window.setTimeout(
        () => nicknameField.value?.input?.focus({ preventScroll: true }),
        900,
    );
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.clearTimeout(tagTimer);
    window.removeEventListener('keydown', onKeydown);
});

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

    if (target === 0) {
        window.setTimeout(
            () => nicknameField.value?.input?.focus({ preventScroll: true }),
            500,
        );
    }
}

function nextFromNickname() {
    if (!hasNickname.value) {
        shake(nicknameField.value?.input);

        return;
    }

    void goToStep(1);
}

/** O sprite escolhido voa em arco até o avatar do chip, que dá um pop. */
function onPicked(index: number, sprite: HTMLImageElement) {
    const target = chip.value?.avatar;
    const option = props.avatarOptions[index];

    if (!target || !option) {
        chipAvatarPath.value = option?.sprite_path ?? null;

        return;
    }

    const from = sprite.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const ghost = sprite.cloneNode() as HTMLImageElement;
    Object.assign(ghost.style, {
        position: 'fixed',
        left: `${from.left}px`,
        top: `${from.top}px`,
        width: `${from.width}px`,
        height: `${from.height}px`,
        zIndex: '60',
        pointerEvents: 'none',
        transform: 'none',
    });
    document.body.appendChild(ghost);

    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);
    const scale = 48 / from.width;
    const flight = animate(
        ghost,
        [
            { transform: 'none' },
            {
                transform: `translate(${dx * 0.45}px, ${dy * 0.5 - 70}px) scale(1.15) rotate(-24deg)`,
                offset: 0.45,
            },
            {
                transform: `translate(${dx}px, ${dy}px) scale(${scale}) rotate(0deg)`,
            },
        ],
        620,
        { easing: 'cubic-bezier(.45,0,.3,1)', fill: 'forwards' },
    );

    const land = () => {
        ghost.remove();
        chipAvatarPath.value = option.sprite_path;
        animate(
            target,
            [{ transform: 'scale(1.35)' }, { transform: 'none' }],
            520,
            {
                easing: spring(320, 12),
                fill: 'none',
            },
        );
    };

    if (flight) {
        flight.onfinish = land;
    } else {
        land();
    }
}

async function finish() {
    if (isMoving.value || profile.processing) {
        return;
    }

    if (profile.avatar_species_id === null) {
        partnerGrid.value?.nudge();

        return;
    }

    profile.nickname = profile.nickname.trim();
    profile.birthdate = birthdate.value;

    try {
        await profile.post(store().url);
    } catch {
        shake(card.value);

        return;
    }

    if (profile.errors.nickname) {
        nicknameError.value = profile.errors.nickname;
        await goToStep(0);

        return;
    }

    if (Object.keys(profile.errors).length > 0) {
        await goToStep(profile.errors.birthdate ? 1 : 2);

        return;
    }

    await leaveToHome();
}

/** Fecha o card na última pill e o chip voa pro canto onde fica o avatar da home. */
async function leaveToHome() {
    isMoving.value = true;
    await morph.collapseToPill(2);
    morph.gulp(2);
    step.value = 3;
    await wait(260);

    const avatar = chip.value?.avatar?.getBoundingClientRect();

    if (avatar) {
        const dx = HOME_AVATAR_CENTER.x - (avatar.left + avatar.width / 2);
        const dy = HOME_AVATAR_CENTER.y - (avatar.top + avatar.height / 2);
        field.converge(HOME_AVATAR_CENTER.x, HOME_AVATAR_CENTER.y, {
            swirl: 0.02,
        });
        animate(
            chip.value?.root,
            [
                { transform: 'none', opacity: 1 },
                {
                    transform: `translate(${dx}px, ${dy}px) scale(.6)`,
                    opacity: 0,
                },
            ],
            620,
            {
                easing: 'cubic-bezier(.6,0,.3,1)',
                fill: 'forwards',
            },
        );
    }

    animate(
        pillsRoot.value,
        [{ opacity: 1 }, { opacity: 0, transform: 'scale(.6)' }],
        300,
        { fill: 'forwards' },
    );
    await wait(560);
    field.fade();
    router.visit(home().url);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key !== 'Enter' || isMoving.value) {
        return;
    }

    if (step.value === 0) {
        event.preventDefault();
        nextFromNickname();
    } else if (step.value === 1) {
        void goToStep(2);
    } else if (step.value === 2) {
        void finish();
    }
}
</script>

<template>
    <Head title="Complete seu perfil" />

    <div
        class="justify-[safe_center] absolute inset-0 z-[3] flex flex-col items-center gap-[18px] overflow-y-auto px-4 py-6"
    >
        <TrainerChip
            ref="chip"
            :nickname="profile.nickname.trim()"
            :tag="tag"
            :avatar-sprite-path="chipAvatarPath"
        />

        <StepPills ref="pills" :count="3" :current="step" />

        <div
            ref="card"
            class="w-full max-w-[440px] rounded-[28px] bg-white px-[30px] pt-[30px] pb-7 shadow-[0_2px_4px_rgba(43,47,54,0.05),0_22px_56px_rgba(43,47,54,0.18)]"
            :class="{ invisible: step === 3 }"
        >
            <div ref="content" class="flex flex-col gap-[22px]">
                <template v-if="step === 0">
                    <h1
                        data-enter
                        class="text-ink text-center text-[27px] leading-[1.15] font-black tracking-[-0.025em] text-balance"
                    >
                        Ei, treinador! Como posso te chamar?
                    </h1>
                    <div data-enter>
                        <EntryField
                            ref="nicknameField"
                            v-model="profile.nickname"
                            label="Nickname"
                            name="nickname"
                            autocomplete="nickname"
                            :maxlength="nicknameMaxLength"
                            :error="nicknameError"
                            @update:model-value="onNicknameInput"
                        />
                    </div>
                    <div data-enter>
                        <PrimaryButton
                            :dimmed="!hasNickname"
                            @click="nextFromNickname"
                        >
                            Continuar
                        </PrimaryButton>
                    </div>
                </template>

                <template v-else-if="step === 1">
                    <div data-enter class="flex flex-col gap-1.5 text-center">
                        <h1
                            class="text-ink text-[27px] leading-[1.15] font-black tracking-[-0.025em] text-balance"
                        >
                            Qual é a sua data de nascimento, treinador?
                        </h1>
                        <p
                            class="text-soft text-[14px] leading-[1.45] font-semibold text-pretty"
                        >
                            Só pra garantir que você já pode aparecer no
                            Pokédex.
                        </p>
                    </div>
                    <div data-enter>
                        <BirthdateWheels
                            v-model:day="day"
                            v-model:month="month"
                            v-model:year="year"
                            :year-range="birthYearRange"
                        />
                        <span
                            v-if="profile.errors.birthdate"
                            role="alert"
                            class="text-brand-ink mt-2 inline-block rounded-full bg-[rgba(242,77,77,0.12)] px-3 py-1.5 text-[12.5px] font-extrabold"
                        >
                            {{ profile.errors.birthdate }}
                        </span>
                    </div>
                    <div data-enter class="grid grid-cols-2 gap-3">
                        <SecondaryButton @click="goToStep(0)"
                            >Voltar</SecondaryButton
                        >
                        <PrimaryButton @click="goToStep(2)"
                            >Continuar</PrimaryButton
                        >
                    </div>
                </template>

                <template v-else-if="step === 2">
                    <div data-enter class="flex flex-col gap-1.5 text-center">
                        <h1
                            class="text-ink text-[27px] leading-[1.15] font-black tracking-[-0.025em]"
                        >
                            Escolha seu parceiro!
                        </h1>
                        <p
                            class="text-soft text-[14px] leading-[1.45] font-semibold text-pretty"
                        >
                            Ele vai te representar pra galera, então escolhe com
                            o coração.
                        </p>
                    </div>
                    <div data-enter>
                        <PartnerGrid
                            ref="partnerGrid"
                            v-model="profile.avatar_species_id"
                            :options="avatarOptions"
                            @picked="onPicked"
                        />
                    </div>
                    <div data-enter class="grid grid-cols-2 gap-3">
                        <SecondaryButton @click="goToStep(1)"
                            >Voltar</SecondaryButton
                        >
                        <PrimaryButton
                            :dimmed="profile.avatar_species_id === null"
                            @click="finish"
                        >
                            Entrar no jogo
                        </PrimaryButton>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
