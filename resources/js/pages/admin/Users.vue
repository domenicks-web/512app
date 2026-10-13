<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/Admin/AdminRoleController';
import AdminShell from '@/components/AdminShell.vue';

type AdminUser = {
    id: number;
    nickname: string | null;
    tag: string | null;
    email: string;
    is_admin: boolean;
    has_completed_profile: boolean;
    invite_code: string | null;
    created_at: string | null;
};

defineProps<{
    users: AdminUser[];
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);
const adminError = computed(
    () => (page.props.errors as Record<string, string>).admin,
);

function displayName(user: AdminUser): string {
    return user.nickname !== null
        ? `${user.nickname}#${user.tag}`
        : '(perfil incompleto)';
}

function confirmGrant(user: AdminUser): boolean {
    return window.confirm(
        `Tornar ${displayName(user)} (${user.email}) admin? Essa conta vai poder criar convites e gerenciar outros admins.`,
    );
}

function confirmRevoke(user: AdminUser): boolean {
    return window.confirm(
        `Remover o acesso de admin de ${displayName(user)} (${user.email})?`,
    );
}
</script>

<template>
    <AdminShell title="Usuários">
        <p
            v-if="adminError"
            class="mb-4 rounded-xl bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-600"
        >
            {{ adminError }}
        </p>

        <ul class="flex flex-col gap-3">
            <li
                v-for="user in users"
                :key="user.id"
                class="bg-surface ring-ink/10 flex flex-wrap items-center gap-3 rounded-2xl p-4 ring-1 ring-inset"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="font-bold"
                            :class="
                                user.has_completed_profile ? '' : 'text-ink/50'
                            "
                            >{{ displayName(user) }}</span
                        >
                        <span
                            v-if="user.is_admin"
                            class="bg-brand rounded-full px-2.5 py-0.5 text-xs font-bold text-white"
                            >Admin</span
                        >
                        <span
                            v-if="user.id === currentUserId"
                            class="bg-ink/10 text-ink/60 rounded-full px-2.5 py-0.5 text-xs font-bold"
                            >Você</span
                        >
                    </div>
                    <p class="text-ink/60 truncate text-xs">
                        {{ user.email }}
                        <span v-if="user.invite_code">
                            · convite {{ user.invite_code }}</span
                        >
                    </p>
                </div>

                <Form
                    v-if="!user.is_admin"
                    :action="store(user.id)"
                    :options="{ preserveScroll: true }"
                    :on-before="() => confirmGrant(user)"
                    #default="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="text-brand ring-brand/30 hover:bg-brand/10 cursor-pointer rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset disabled:opacity-50"
                    >
                        Tornar admin
                    </button>
                </Form>

                <Form
                    v-else-if="user.id !== currentUserId"
                    :action="destroy(user.id)"
                    :options="{ preserveScroll: true }"
                    :on-before="() => confirmRevoke(user)"
                    #default="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="cursor-pointer rounded-full px-3 py-1 text-xs font-bold text-red-600 ring-1 ring-red-500/30 ring-inset hover:bg-red-500/10 disabled:opacity-50"
                    >
                        Remover admin
                    </button>
                </Form>
            </li>
        </ul>
    </AdminShell>
</template>
