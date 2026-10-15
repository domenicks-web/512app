<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import PreferenceSwitch from '@/components/PreferenceSwitch.vue';
import { onDeviceTilt } from '@/lib/deviceTilt';
import {
    hasMotionSensor,
    isMotionEnabled,
    motionNeedsPermission,
    toggleMotion,
} from '@/lib/motionPreference';
import { isSoundMuted, setSoundMuted } from '@/lib/soundPreference';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { show as verifyShow } from '@/routes/verify';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Ajustes: atalho pro perfil, sons e movimento das cartas (guardados neste
 * aparelho), verificar um
 * pack e sair. O
 * painel de admin não aparece aqui: quem é admin entra por `/admin`.
 */
const page = usePage<{
    auth: {
        user: { nickname: string | null; tag: string | null } | null;
        avatarSpritePath: string | null;
    };
}>();

// Lido só no navegador: no servidor não existe localStorage.
const isSoundOn = ref(true);
const isMotionOn = ref(false);
const hasSensor = ref(false);
const needsPermission = ref(false);
onMounted(() => {
    isSoundOn.value = !isSoundMuted();
    hasSensor.value = hasMotionSensor();
    needsPermission.value = motionNeedsPermission();
    isMotionOn.value = isMotionEnabled();
});

async function toggleMotionSwitch() {
    isMotionOn.value = await toggleMotion(!isMotionOn.value);
}

// Teste ao vivo: a bolinha anda com o celular, pra ver que o sensor lê.
const tilt = ref<{ gx: number; gy: number } | null>(null);
const stopTilt = onDeviceTilt((value) => (tilt.value = { ...value }));
onBeforeUnmount(stopTilt);

function toggleSound() {
    isSoundOn.value = !isSoundOn.value;
    setSoundMuted(!isSoundOn.value);
}

const card =
    'flex items-center gap-3.5 rounded-[22px] bg-white p-3 pr-5 shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]';
</script>

<template>
    <GroupScreen title="Ajustes">
        <div class="flex flex-col gap-3">
            <Link
                data-enter
                :href="profileEdit()"
                :prefetch="['hover', 'click']"
                cache-for="10s"
                :class="card"
                class="transition-transform active:scale-[0.98]"
            >
                <TrainerAvatar
                    :sprite-path="page.props.auth.avatarSpritePath"
                    highlighted
                />
                <div class="flex min-w-0 flex-1 flex-col gap-px">
                    <span class="text-ink truncate text-[15.5px] font-black"
                        >{{ page.props.auth.user?.nickname
                        }}<span class="text-faint-2"
                            >#{{ page.props.auth.user?.tag }}</span
                        ></span
                    >
                    <span class="text-soft text-xs font-semibold"
                        >Editar perfil</span
                    >
                </div>
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#9aa0a8"
                    stroke-width="2.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <div
                data-enter
                :class="card"
                class="flex-col !items-stretch gap-4 p-4 pl-5"
            >
                <PreferenceSwitch
                    title="Sons"
                    hint="Vale neste aparelho"
                    :checked="isSoundOn"
                    @toggle="toggleSound"
                />
                <PreferenceSwitch
                    title="Mexer as cartas com o celular"
                    :disabled="!hasSensor"
                    :hint="
                        !hasSensor
                            ? 'Precisa de https: liga depois do deploy'
                            : needsPermission && !isMotionOn
                              ? 'O celular vai pedir permissão'
                              : 'O brilho das cartas segue o celular'
                    "
                    :checked="hasSensor && isMotionOn"
                    @toggle="toggleMotionSwitch"
                />
                <div
                    v-if="hasSensor && isMotionOn"
                    class="flex items-center gap-3 rounded-2xl bg-[#f4f5f6] px-3.5 py-2.5"
                >
                    <span
                        class="relative size-9 shrink-0 rounded-full bg-white shadow-[inset_0_0_0_1.5px_#dfe2e6]"
                        aria-hidden="true"
                    >
                        <span
                            class="absolute top-1/2 left-1/2 -mt-1.5 -ml-1.5 size-3 rounded-full bg-[#F24D4D] transition-transform duration-100"
                            :style="{
                                transform: `translate(${(tilt?.gx ?? 0) * 12}px, ${(tilt?.gy ?? 0) * 12}px)`,
                            }"
                        />
                    </span>
                    <span class="text-xs font-bold text-[#5a6069]">{{
                        tilt && (tilt.gx !== 0 || tilt.gy !== 0)
                            ? 'Lendo o celular. Incline pra ver a bolinha andar.'
                            : 'Incline o celular. Se a bolinha não andar, o sensor não chegou.'
                    }}</span>
                </div>
            </div>

            <Link
                data-enter
                :href="verifyShow()"
                :prefetch="['hover', 'click']"
                :class="card"
                class="p-4 pl-5 transition-transform active:scale-[0.98]"
            >
                <div class="flex min-w-0 flex-1 flex-col gap-px">
                    <span class="text-ink text-[15.5px] font-black"
                        >Verificar um pack</span
                    >
                    <span class="text-soft text-xs font-semibold"
                        >Refaça a conta de qualquer abertura</span
                    >
                </div>
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#9aa0a8"
                    stroke-width="2.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <Link
                data-enter
                :href="logout()"
                as="button"
                class="text-brand-ink mt-3 h-12 cursor-pointer rounded-full bg-white text-[15px] font-extrabold shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)] transition-transform active:scale-[0.98]"
            >
                Sair
            </Link>
        </div>
    </GroupScreen>
</template>
