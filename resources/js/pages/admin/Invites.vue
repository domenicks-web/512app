<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    revoke,
    store,
} from '@/actions/App/Http/Controllers/Admin/InviteController';
import AdminShell from '@/components/AdminShell.vue';
import AppButton from '@/components/AppButton.vue';

type InviteStatus = 'active' | 'exhausted' | 'expired' | 'revoked';

type Invite = {
    id: number;
    code: string;
    status: InviteStatus;
    max_uses: number | null;
    uses_count: number;
    expires_at: string | null;
    revoked_at: string | null;
    created_at: string;
    created_by: string;
    redemptions: { user: string; redeemed_at: string }[];
};

defineProps<{
    invites: Invite[];
    limits: { max_uses: number; expires_in_days: number };
}>();

const page = usePage<{ flash: { created_invite_code: string | null } }>();
const createdCode = computed(() => page.props.flash.created_invite_code);

const statusLabels: Record<InviteStatus, string> = {
    active: 'Ativo',
    exhausted: 'Esgotado',
    expired: 'Expirado',
    revoked: 'Revogado',
};

const statusClasses: Record<InviteStatus, string> = {
    active: 'bg-green-500/15 text-green-700 dark:text-green-400',
    exhausted: 'bg-ink/10 text-ink/60',
    expired: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    revoked: 'bg-red-500/15 text-red-700 dark:text-red-400',
};

const copiedCode = ref<string | null>(null);

async function copyCode(code: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(code);
        copiedCode.value = code;
        setTimeout(() => {
            if (copiedCode.value === code) {
                copiedCode.value = null;
            }
        }, 1500);
    } catch {
        // Sem permissão de clipboard (http sem localhost, por exemplo): o
        // código continua visível na tela pra copiar na mão.
    }
}

function formatDate(iso: string | null): string {
    return iso === null
        ? '—'
        : new Date(iso).toLocaleString('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          });
}

function confirmRevoke(code: string): boolean {
    return window.confirm(
        `Revogar o convite ${code}? Ninguém mais vai conseguir se cadastrar com ele.`,
    );
}
</script>

<template>
    <AdminShell title="Convites">
        <section
            class="bg-surface ring-ink/10 mb-8 rounded-2xl p-5 ring-1 ring-inset"
        >
            <h2 class="mb-4 font-bold">Novo convite</h2>

            <Form
                :action="store()"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="flex flex-wrap items-end gap-4"
                #default="{ errors, processing }"
            >
                <label class="flex flex-col gap-1.5">
                    <span class="text-ink/70 text-sm font-bold"
                        >Limite de usos</span
                    >
                    <input
                        name="max_uses"
                        type="number"
                        min="1"
                        :max="limits.max_uses"
                        value="1"
                        placeholder="vazio = infinito"
                        class="border-ink/15 bg-ink/5 focus:border-brand w-44 rounded-full border px-4 py-2 text-sm outline-none"
                    />
                    <span
                        v-if="errors.max_uses"
                        class="text-xs font-semibold text-red-500"
                        >{{ errors.max_uses }}</span
                    >
                </label>

                <label class="flex flex-col gap-1.5">
                    <span class="text-ink/70 text-sm font-bold"
                        >Expira em (dias)</span
                    >
                    <input
                        name="expires_in_days"
                        type="number"
                        min="1"
                        :max="limits.expires_in_days"
                        placeholder="vazio = nunca"
                        class="border-ink/15 bg-ink/5 focus:border-brand w-44 rounded-full border px-4 py-2 text-sm outline-none"
                    />
                    <span
                        v-if="errors.expires_in_days"
                        class="text-xs font-semibold text-red-500"
                        >{{ errors.expires_in_days }}</span
                    >
                </label>

                <AppButton type="submit" :disabled="processing">
                    Gerar convite
                </AppButton>
            </Form>

            <p class="text-ink/50 mt-3 text-xs">
                Deixe o limite vazio pra um convite sem limite de usos.
            </p>

            <div
                v-if="createdCode"
                class="mt-4 flex flex-wrap items-center gap-3 rounded-xl bg-green-500/10 px-4 py-3"
            >
                <span class="text-sm">Convite criado:</span>
                <code class="text-ink font-mono text-lg font-bold">{{
                    createdCode
                }}</code>
                <button
                    type="button"
                    class="text-brand cursor-pointer text-sm font-bold hover:underline"
                    @click="copyCode(createdCode)"
                >
                    {{ copiedCode === createdCode ? 'Copiado!' : 'Copiar' }}
                </button>
            </div>
        </section>

        <p v-if="invites.length === 0" class="text-ink/60 text-sm">
            Nenhum convite criado ainda.
        </p>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="invite in invites"
                :key="invite.id"
                class="bg-surface ring-ink/10 rounded-2xl p-4 ring-1 ring-inset"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <code class="text-ink font-mono text-base font-bold">{{
                        invite.code
                    }}</code>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                        :class="statusClasses[invite.status]"
                    >
                        {{ statusLabels[invite.status] }}
                    </span>
                    <button
                        type="button"
                        class="text-brand cursor-pointer text-xs font-bold hover:underline"
                        @click="copyCode(invite.code)"
                    >
                        {{ copiedCode === invite.code ? 'Copiado!' : 'Copiar' }}
                    </button>

                    <Form
                        v-if="invite.status !== 'revoked'"
                        :action="revoke(invite.id)"
                        :options="{ preserveScroll: true }"
                        :on-before="() => confirmRevoke(invite.code)"
                        class="ml-auto"
                        #default="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            class="cursor-pointer rounded-full px-3 py-1 text-xs font-bold text-red-600 ring-1 ring-red-500/30 ring-inset hover:bg-red-500/10 disabled:opacity-50"
                        >
                            Revogar
                        </button>
                    </Form>
                </div>

                <dl
                    class="text-ink/70 mt-3 grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-4"
                >
                    <div>
                        <dt class="text-ink/50">Usos</dt>
                        <dd class="font-bold">
                            {{ invite.uses_count }} /
                            {{ invite.max_uses ?? '∞' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-ink/50">Expira</dt>
                        <dd class="font-bold">
                            {{
                                invite.expires_at
                                    ? formatDate(invite.expires_at)
                                    : 'Nunca'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-ink/50">Criado por</dt>
                        <dd class="font-bold">{{ invite.created_by }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink/50">Criado em</dt>
                        <dd class="font-bold">
                            {{ formatDate(invite.created_at) }}
                        </dd>
                    </div>
                </dl>

                <details v-if="invite.redemptions.length > 0" class="mt-3">
                    <summary
                        class="text-ink/60 cursor-pointer text-xs font-bold"
                    >
                        Quem usou ({{ invite.redemptions.length }})
                    </summary>
                    <ul class="mt-2 flex flex-col gap-1 text-xs">
                        <li
                            v-for="redemption in invite.redemptions"
                            :key="redemption.user + redemption.redeemed_at"
                        >
                            <span class="font-bold">{{ redemption.user }}</span>
                            <span class="text-ink/50">
                                · {{ formatDate(redemption.redeemed_at) }}</span
                            >
                        </li>
                    </ul>
                </details>
            </li>
        </ul>
    </AdminShell>
</template>
