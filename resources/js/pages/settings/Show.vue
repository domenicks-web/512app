<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { isSoundMuted, setSoundMuted } from '@/lib/soundPreference';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Ajustes: atalho pro perfil, sons (guardado neste aparelho) e sair. O
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
onMounted(() => (isSoundOn.value = !isSoundMuted()));

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

            <label data-enter :class="card" class="cursor-pointer p-4 pl-5">
                <div class="flex min-w-0 flex-1 flex-col gap-px">
                    <span class="text-ink text-[15.5px] font-black">Sons</span>
                    <span class="text-soft text-xs font-semibold"
                        >Vale neste aparelho</span
                    >
                </div>
                <span
                    class="relative inline-flex h-[28px] w-[48px] shrink-0 items-center rounded-full transition-colors has-[:focus-visible]:shadow-[0_0_0_3px_rgba(242,77,77,0.35)]"
                    :class="isSoundOn ? 'bg-brand' : 'bg-[#dfe2e6]'"
                >
                    <input
                        type="checkbox"
                        role="switch"
                        class="absolute inset-0 size-full cursor-pointer opacity-0"
                        :checked="isSoundOn"
                        :aria-checked="isSoundOn"
                        aria-label="Sons"
                        @change="toggleSound"
                    />
                    <span
                        class="pointer-events-none block size-[22px] rounded-full bg-white shadow-[0_1px_3px_rgba(28,32,38,0.25)] transition-transform"
                        :class="
                            isSoundOn
                                ? 'translate-x-[23px]'
                                : 'translate-x-[3px]'
                        "
                    />
                </span>
            </label>

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
