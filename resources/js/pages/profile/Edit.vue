<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BirthdateWheels from '@/components/entry/BirthdateWheels.vue';
import EntryField from '@/components/entry/EntryField.vue';
import PartnerGrid from '@/components/entry/PartnerGrid.vue';
import type { PartnerOption } from '@/components/entry/PartnerGrid.vue';
import PasswordField from '@/components/entry/PasswordField.vue';
import ConfirmDialog from '@/components/group/ConfirmDialog.vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import { flySpriteTo } from '@/composables/useSpriteFlight';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { update as updateAvatar } from '@/routes/profile/avatar';
import { update as updateBirthdate } from '@/routes/profile/birthdate';
import {
    destroy as cancelEmailChange,
    store as requestEmailChange,
} from '@/routes/profile/email';
import { update as updateNickname } from '@/routes/profile/nickname';
import { update as updatePassword } from '@/routes/profile/password';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Editar perfil: avatar, nickname (a cada `nicknameCooldownDays`), data de
 * nascimento (corrigível uma vez), email (com link pro endereço novo) e
 * senha. Nickname, data e senha passam por um diálogo de confirmação. Toda
 * regra é do servidor; a tela só mostra o estado e os avisos.
 */
const props = defineProps<{
    profile: {
        nickname: string;
        tag: string;
        avatarSpeciesId: number | null;
        avatarIsShiny: boolean;
        birthdate: string | null;
        isBirthdateLocked: boolean;
        email: string;
        isEmailVerified: boolean;
        pendingEmail: string | null;
        nicknameChangeableAt: string | null;
    };
    avatarOptions: PartnerOption[];
    collectionAvatarOptions: PartnerOption[];
    nicknameCooldownDays: number;
    nicknameMaxLength: number;
    birthYearRange: [number, number];
    emailChangeLinkMinutes: number;
}>();

const page = usePage<{ flash: { profile_status: string | null } }>();
const status = computed(() => page.props.flash.profile_status);

// O avatar do cartão só troca quando o sprite escolhido pousa nele.
const allAvatarOptions = computed(() => [
    ...props.avatarOptions,
    ...props.collectionAvatarOptions,
]);
const avatarSprite = ref(
    allAvatarOptions.value.find(
        (option) =>
            option.id === props.profile.avatarSpeciesId &&
            Boolean(option.is_shiny) === props.profile.avatarIsShiny,
    )?.sprite_path ?? null,
);
const avatarTarget = ref<InstanceType<typeof TrainerAvatar> | null>(null);

function onPartnerPicked(
    options: PartnerOption[],
    index: number,
    sprite: HTMLElement,
) {
    const option = options[index];
    const target = avatarTarget.value?.$el as HTMLElement | undefined;

    if (!option || !target) {
        avatarSprite.value = option?.sprite_path ?? null;

        return;
    }

    flySpriteTo(sprite, target, () => {
        avatarSprite.value = option.sprite_path;
    });
}

const formatDayMonth = (isoDate: string) => {
    const [, month, day] = isoDate.split('-');

    return `${day}/${month}`;
};

const options = { preserveScroll: true };

// Avatar
const avatarForm = useForm({
    avatar_species_id: props.profile.avatarSpeciesId,
    avatar_is_shiny: props.profile.avatarIsShiny,
});
const isAvatarUnchanged = computed(
    () =>
        avatarForm.avatar_species_id === props.profile.avatarSpeciesId &&
        avatarForm.avatar_is_shiny === props.profile.avatarIsShiny,
);
const saveAvatar = () => avatarForm.submit(updateAvatar(), options);

// Nickname
const nicknameForm = useForm({ nickname: props.profile.nickname });
const isNicknameDialogOpen = ref(false);
const openNicknameDialog = () => {
    if (nicknameForm.nickname.trim() !== props.profile.nickname) {
        isNicknameDialogOpen.value = true;
    }
};
const saveNickname = () =>
    nicknameForm.submit(updateNickname(), {
        ...options,
        onFinish: () => (isNicknameDialogOpen.value = false),
    });

// Data de nascimento (o mês das rodas começa em 0)
const [initialYear, initialMonth, initialDay] = (
    props.profile.birthdate ?? `${props.birthYearRange[1] - 20}-01-01`
)
    .split('-')
    .map(Number);
const day = ref(initialDay);
const month = ref(initialMonth - 1);
const year = ref(initialYear);
const birthdateForm = useForm({ birthdate: '' });
const isBirthdateDialogOpen = ref(false);
const pickedBirthdate = computed(
    () =>
        `${year.value}-${String(month.value + 1).padStart(2, '0')}-${String(day.value).padStart(2, '0')}`,
);
const saveBirthdate = () =>
    birthdateForm
        .transform(() => ({ birthdate: pickedBirthdate.value }))
        .submit(updateBirthdate(), {
            ...options,
            onFinish: () => (isBirthdateDialogOpen.value = false),
        });

// Email
const emailForm = useForm({ email: '', current_password: '' });
const saveEmail = () =>
    emailForm.submit(requestEmailChange(), {
        ...options,
        onSuccess: () => emailForm.reset(),
        onError: () => emailForm.reset('current_password'),
    });
const cancelForm = useForm({});
const cancelEmail = () => cancelForm.submit(cancelEmailChange(), options);

// Senha
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const isPasswordDialogOpen = ref(false);
const passwordRequirements = computed(() => [
    { label: '12 caracteres ou mais', met: passwordForm.password.length >= 12 },
    {
        label: 'letra maiúscula e minúscula',
        met:
            /[a-z]/.test(passwordForm.password) &&
            /[A-Z]/.test(passwordForm.password),
    },
    { label: 'um número', met: /\d/.test(passwordForm.password) },
    { label: 'um símbolo', met: /[^A-Za-z0-9]/.test(passwordForm.password) },
    {
        label: 'as duas senhas novas iguais',
        met:
            passwordForm.password !== '' &&
            passwordForm.password === passwordForm.password_confirmation,
    },
]);
const savePassword = () =>
    passwordForm.submit(updatePassword(), {
        ...options,
        onSuccess: () => passwordForm.reset(),
        onError: () => passwordForm.reset('current_password'),
        onFinish: () => (isPasswordDialogOpen.value = false),
    });

const card =
    'flex flex-col gap-4 rounded-[22px] bg-white p-5 shadow-[0_2px_4px_rgba(28,32,38,0.06),0_14px_30px_rgba(28,32,38,0.08)]';
const sectionTitle = 'text-ink text-[17px] font-black tracking-[-0.01em]';
const help = 'text-soft text-[13px] leading-relaxed font-semibold';
const errorPill =
    'text-brand-ink self-start rounded-full bg-[rgba(242,77,77,0.12)] px-3 py-1.5 text-[12.5px] font-extrabold';
const primaryButton =
    'bg-brand disabled:bg-brand-disabled h-12 cursor-pointer self-start rounded-full px-6 text-[15px] font-extrabold text-white shadow-[0_10px_24px_rgba(242,77,77,0.42)] transition-transform active:scale-[.97] disabled:cursor-default disabled:shadow-none';
</script>

<template>
    <GroupScreen title="Editar perfil">
        <div class="mx-auto flex w-full max-w-[560px] flex-col gap-5">
            <p
                v-if="status"
                role="status"
                class="bg-online/12 text-online self-start rounded-full px-4 py-2 text-[13px] font-extrabold"
            >
                {{ status }}
            </p>

            <section data-enter :class="card" aria-labelledby="avatar-title">
                <div class="flex items-center gap-4">
                    <TrainerAvatar
                        ref="avatarTarget"
                        :sprite-path="avatarSprite"
                        highlighted
                    />
                    <div class="flex flex-col">
                        <h2 id="avatar-title" :class="sectionTitle">
                            Parceiro
                        </h2>
                        <span :class="help">Troque quando quiser.</span>
                    </div>
                </div>
                <PartnerGrid
                    v-model="avatarForm.avatar_species_id"
                    v-model:shiny="avatarForm.avatar_is_shiny"
                    :options="avatarOptions"
                    @picked="
                        (index, sprite) =>
                            onPartnerPicked(avatarOptions, index, sprite)
                    "
                />
                <div class="flex flex-col gap-2">
                    <h3 class="text-ink text-[14px] font-black">
                        Da sua coleção
                    </h3>
                    <p
                        v-if="collectionAvatarOptions.length === 0"
                        :class="help"
                    >
                        Abra packs: toda espécie que você tiver vira opção de
                        parceiro, e a versão shiny também, se tirar uma.
                    </p>
                    <div
                        v-else
                        class="max-h-[284px] overflow-y-auto overscroll-contain rounded-[18px] py-2"
                    >
                        <PartnerGrid
                            v-model="avatarForm.avatar_species_id"
                            v-model:shiny="avatarForm.avatar_is_shiny"
                            :options="collectionAvatarOptions"
                            @picked="
                                (index, sprite) =>
                                    onPartnerPicked(
                                        collectionAvatarOptions,
                                        index,
                                        sprite,
                                    )
                            "
                        />
                    </div>
                </div>
                <span
                    v-if="avatarForm.errors.avatar_species_id"
                    :class="errorPill"
                    >{{ avatarForm.errors.avatar_species_id }}</span
                >
                <button
                    type="button"
                    :class="primaryButton"
                    :disabled="avatarForm.processing || isAvatarUnchanged"
                    @click="saveAvatar"
                >
                    Salvar parceiro
                </button>
            </section>

            <section data-enter :class="card" aria-labelledby="nickname-title">
                <h2 id="nickname-title" :class="sectionTitle">Nickname</h2>
                <template v-if="profile.nicknameChangeableAt">
                    <p class="text-ink text-[15.5px] font-black">
                        {{ profile.nickname
                        }}<span class="text-faint-2">#{{ profile.tag }}</span>
                    </p>
                    <p :class="help">
                        Você trocou de nickname há pouco. Poderá trocar de novo
                        em
                        <strong class="text-ink">{{
                            formatDayMonth(profile.nicknameChangeableAt)
                        }}</strong
                        >.
                    </p>
                </template>
                <form
                    v-else
                    class="flex flex-col gap-3"
                    @submit.prevent="openNicknameDialog"
                >
                    <EntryField
                        v-model="nicknameForm.nickname"
                        label="Como o grupo te chama"
                        name="nickname"
                        autocomplete="nickname"
                        :maxlength="nicknameMaxLength"
                        :error="nicknameForm.errors.nickname"
                    />
                    <p :class="help">
                        Sua tag hoje é
                        <strong class="text-ink">#{{ profile.tag }}</strong
                        >. Depois de trocar, você só poderá trocar de novo daqui
                        a {{ nicknameCooldownDays }} dias.
                    </p>
                    <button
                        type="submit"
                        :class="primaryButton"
                        :disabled="
                            nicknameForm.processing ||
                            nicknameForm.nickname.trim() === profile.nickname
                        "
                    >
                        Trocar nickname
                    </button>
                </form>
            </section>

            <section data-enter :class="card" aria-labelledby="birthdate-title">
                <h2 id="birthdate-title" :class="sectionTitle">
                    Data de nascimento
                </h2>
                <template v-if="profile.isBirthdateLocked">
                    <p class="text-ink text-[15.5px] font-black">
                        {{
                            profile.birthdate
                                ? profile.birthdate
                                      .split('-')
                                      .reverse()
                                      .join('/')
                                : '—'
                        }}
                    </p>
                    <p :class="help">
                        Já corrigida. Se precisar mudar de novo, fale com um
                        admin do grupo.
                    </p>
                </template>
                <template v-else>
                    <p
                        class="bg-field text-muted rounded-[16px] px-4 py-3 text-[13px] leading-relaxed font-bold"
                    >
                        Você só pode corrigir a data de nascimento
                        <strong class="text-ink">uma vez</strong>. Depois disso,
                        só um admin consegue liberar outra correção.
                    </p>
                    <BirthdateWheels
                        v-model:day="day"
                        v-model:month="month"
                        v-model:year="year"
                        :year-range="birthYearRange"
                    />
                    <span
                        v-if="birthdateForm.errors.birthdate"
                        :class="errorPill"
                        >{{ birthdateForm.errors.birthdate }}</span
                    >
                    <button
                        type="button"
                        :class="primaryButton"
                        :disabled="
                            birthdateForm.processing ||
                            pickedBirthdate === profile.birthdate
                        "
                        @click="isBirthdateDialogOpen = true"
                    >
                        Corrigir data
                    </button>
                </template>
            </section>

            <section data-enter :class="card" aria-labelledby="email-title">
                <div class="flex flex-col gap-1">
                    <h2 id="email-title" :class="sectionTitle">Email</h2>
                    <p
                        class="text-ink flex flex-wrap items-center gap-2 text-[15px] font-bold"
                    >
                        {{ profile.email }}
                        <span
                            v-if="!profile.isEmailVerified"
                            class="bg-field text-soft rounded-full px-2.5 py-0.5 text-[11.5px] font-extrabold"
                            >não confirmado</span
                        >
                    </p>
                </div>
                <div
                    v-if="profile.pendingEmail"
                    class="bg-field flex flex-col gap-2 rounded-[16px] px-4 py-3"
                >
                    <p
                        class="text-muted text-[13.5px] leading-relaxed font-bold"
                    >
                        Mandamos um link pra
                        <strong class="text-ink">{{
                            profile.pendingEmail
                        }}</strong
                        >. Ele vale por {{ emailChangeLinkMinutes }} minutos; o
                        email só troca depois do clique.
                    </p>
                    <button
                        type="button"
                        :disabled="cancelForm.processing"
                        class="text-brand-ink cursor-pointer self-start text-[13px] font-extrabold underline-offset-2 hover:underline"
                        @click="cancelEmail"
                    >
                        Cancelar troca
                    </button>
                </div>
                <form class="flex flex-col gap-3" @submit.prevent="saveEmail">
                    <EntryField
                        v-model="emailForm.email"
                        label="Email novo"
                        name="email"
                        type="email"
                        autocomplete="email"
                        :error="emailForm.errors.email"
                    />
                    <PasswordField
                        v-model="emailForm.current_password"
                        label="Senha atual"
                        name="email_current_password"
                        :error="emailForm.errors.current_password"
                    />
                    <p :class="help">
                        Vamos mandar um link pro email novo, e um aviso pro
                        atual quando a troca acontecer.
                    </p>
                    <button
                        type="submit"
                        :class="primaryButton"
                        :disabled="
                            emailForm.processing ||
                            emailForm.email === '' ||
                            emailForm.current_password === ''
                        "
                    >
                        Mandar link
                    </button>
                </form>
            </section>

            <section data-enter :class="card" aria-labelledby="password-title">
                <h2 id="password-title" :class="sectionTitle">Senha</h2>
                <form
                    class="flex flex-col gap-3"
                    @submit.prevent="isPasswordDialogOpen = true"
                >
                    <PasswordField
                        v-model="passwordForm.current_password"
                        label="Senha atual"
                        name="current_password"
                        :error="passwordForm.errors.current_password"
                    />
                    <PasswordField
                        v-model="passwordForm.password"
                        label="Senha nova"
                        name="password"
                        autocomplete="new-password"
                        :error="passwordForm.errors.password"
                    />
                    <PasswordField
                        v-model="passwordForm.password_confirmation"
                        label="Repita a senha nova"
                        name="password_confirmation"
                        autocomplete="new-password"
                    />
                    <ul
                        class="flex flex-col gap-1.5"
                        aria-label="Requisitos da senha"
                    >
                        <li
                            v-for="requirement in passwordRequirements"
                            :key="requirement.label"
                            class="flex items-center gap-2 text-[13px] font-bold transition-colors"
                            :class="
                                requirement.met ? 'text-online' : 'text-faint'
                            "
                        >
                            <span
                                class="flex size-[18px] items-center justify-center rounded-full text-[11px] font-black text-white transition-colors"
                                :class="
                                    requirement.met ? 'bg-online' : 'bg-track'
                                "
                                aria-hidden="true"
                                >✓</span
                            >
                            {{ requirement.label }}
                        </li>
                    </ul>
                    <button
                        type="submit"
                        :class="primaryButton"
                        :disabled="
                            passwordForm.processing ||
                            passwordForm.current_password === '' ||
                            !passwordRequirements.every((r) => r.met)
                        "
                    >
                        Trocar senha
                    </button>
                </form>
            </section>
        </div>

        <ConfirmDialog
            :open="isNicknameDialogOpen"
            title="Trocar de nickname?"
            confirm-label="Trocar"
            :processing="nicknameForm.processing"
            @confirm="saveNickname"
            @cancel="isNicknameDialogOpen = false"
        >
            Você vai virar
            <strong class="text-ink">{{ nicknameForm.nickname.trim() }}</strong
            >. Depois disso, só dá pra trocar de novo daqui a
            {{ nicknameCooldownDays }} dias. Se esse nickname já existir com a
            tag #{{ profile.tag }}, você ganha uma tag nova. Seus amigos
            continuam amigos.
        </ConfirmDialog>

        <ConfirmDialog
            :open="isBirthdateDialogOpen"
            title="Corrigir a data de nascimento?"
            confirm-label="Corrigir"
            :processing="birthdateForm.processing"
            @confirm="saveBirthdate"
            @cancel="isBirthdateDialogOpen = false"
        >
            A data vai ficar
            <strong class="text-ink">{{
                pickedBirthdate.split('-').reverse().join('/')
            }}</strong
            >. Essa é a única correção: depois, só um admin libera outra.
        </ConfirmDialog>

        <ConfirmDialog
            :open="isPasswordDialogOpen"
            title="Trocar a senha?"
            confirm-label="Trocar senha"
            danger
            :processing="passwordForm.processing"
            @confirm="savePassword"
            @cancel="isPasswordDialogOpen = false"
        >
            Você vai sair de todos os outros aparelhos e vai receber um aviso no
            seu email.
        </ConfirmDialog>
    </GroupScreen>
</template>
