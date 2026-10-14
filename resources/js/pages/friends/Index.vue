<script setup lang="ts">
import { Form, Link, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TrainerAvatar from '@/components/group/TrainerAvatar.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import { formatFriendsOnline } from '@/lib/format';
import { destroy } from '@/routes/friends';
import { accept, store } from '@/routes/friends/requests';
import { show as trainerShow } from '@/routes/trainer';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Amigos de verdade: adicionar por nickname#tag, pedidos recebidos
 * (aceitar/recusar), enviados (cancelar) e a lista com o ponto online.
 * Desfazer amizade pede confirmação no próprio cartão. A presença
 * recarrega sozinha a cada `presencePollSeconds`.
 */
type Friend = {
    friendshipId: number;
    userId: number;
    nickname: string;
    tag: string;
    avatarSpritePath: string | null;
    ownedSpecies: number;
    isOnline: boolean;
};

type PendingRequest = {
    friendshipId: number;
    nickname: string;
    tag: string;
    avatarSpritePath: string | null;
    sentAgo: string;
};

const props = defineProps<{
    friends: Friend[];
    incoming: PendingRequest[];
    outgoing: PendingRequest[];
    presencePollSeconds: number;
}>();

usePoll(props.presencePollSeconds * 1000, {
    only: ['friends', 'incoming', 'outgoing', 'pendingFriendRequests'],
});

const confirmingRemoval = ref<number | null>(null);

const friendsOnline = computed(
    () => props.friends.filter((friend) => friend.isOnline).length,
);

const cardShadow =
    'shadow-[0_2px_4px_rgba(28,32,38,0.05),0_8px_18px_rgba(28,32,38,0.07)]';
</script>

<template>
    <GroupScreen
        title="Amigos"
        :subtitle="`${friends.length} ${friends.length === 1 ? 'amigo' : 'amigos'} · ${formatFriendsOnline(friendsOnline)}`"
    >
        <div class="flex flex-col gap-7">
            <Form
                data-enter
                :action="store()"
                reset-on-success
                class="flex flex-col gap-2"
                #default="{ errors, processing }"
            >
                <label
                    for="friend-handle"
                    class="text-muted text-[13.5px] font-extrabold"
                    >Adicionar amigo</label
                >
                <div class="flex gap-2">
                    <input
                        id="friend-handle"
                        name="handle"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="nickname#1234"
                        class="text-ink placeholder:text-placeholder bg-field focus:border-brand h-[52px] min-w-0 flex-1 rounded-full border-2 px-5 text-[15px] font-semibold transition-[border-color,background,box-shadow] outline-none focus:bg-white focus:shadow-[0_0_0_6px_rgba(242,77,77,0.12)]"
                        :class="
                            errors.handle
                                ? 'border-brand'
                                : 'border-transparent'
                        "
                    />
                    <button
                        type="submit"
                        :disabled="processing"
                        class="bg-brand disabled:bg-brand-disabled h-[52px] shrink-0 cursor-pointer rounded-full px-6 text-[15px] font-extrabold text-white shadow-[0_10px_24px_rgba(242,77,77,0.42)] transition-transform active:scale-[.97]"
                    >
                        Enviar
                    </button>
                </div>
                <span
                    v-if="errors.handle"
                    role="alert"
                    class="text-brand-ink self-start rounded-full bg-[rgba(242,77,77,0.12)] px-3 py-1.5 text-[12.5px] font-extrabold"
                    >{{ errors.handle }}</span
                >
            </Form>

            <section v-if="incoming.length" class="flex flex-col gap-3">
                <h2
                    data-enter
                    class="text-muted flex items-center gap-2 text-[13.5px] font-extrabold"
                >
                    <span class="bg-brand size-3 rounded" aria-hidden="true" />
                    Pedidos recebidos
                </h2>
                <article
                    v-for="request in incoming"
                    :key="request.friendshipId"
                    data-enter
                    class="flex flex-wrap items-center gap-3.5 rounded-[22px] bg-white p-3 pr-4"
                    :class="cardShadow"
                >
                    <TrainerAvatar
                        :sprite-path="request.avatarSpritePath"
                        size="sm"
                    />
                    <div class="flex min-w-0 flex-1 flex-col">
                        <span class="text-ink truncate text-[15px] font-black"
                            >{{ request.nickname
                            }}<span class="text-faint-2"
                                >#{{ request.tag }}</span
                            ></span
                        >
                        <span class="text-faint text-xs font-semibold">{{
                            request.sentAgo
                        }}</span>
                    </div>
                    <div class="flex gap-2">
                        <Form
                            :action="destroy(request.friendshipId)"
                            #default="{ processing }"
                        >
                            <button
                                type="submit"
                                :disabled="processing"
                                class="text-muted border-line-strong h-10 cursor-pointer rounded-full border-2 bg-white px-4 text-sm font-extrabold"
                            >
                                Recusar
                            </button>
                        </Form>
                        <Form
                            :action="accept(request.friendshipId)"
                            #default="{ processing }"
                        >
                            <button
                                type="submit"
                                :disabled="processing"
                                class="bg-brand h-10 cursor-pointer rounded-full px-4 text-sm font-extrabold text-white"
                            >
                                Aceitar
                            </button>
                        </Form>
                    </div>
                </article>
            </section>

            <section v-if="outgoing.length" class="flex flex-col gap-3">
                <h2
                    data-enter
                    class="text-muted flex items-center gap-2 text-[13.5px] font-extrabold"
                >
                    <span
                        class="bg-faint-2 size-3 rounded"
                        aria-hidden="true"
                    />
                    Pedidos enviados
                </h2>
                <article
                    v-for="request in outgoing"
                    :key="request.friendshipId"
                    data-enter
                    class="flex items-center gap-3.5 rounded-[22px] bg-white/70 p-3 pr-4"
                >
                    <TrainerAvatar
                        :sprite-path="request.avatarSpritePath"
                        size="sm"
                    />
                    <div class="flex min-w-0 flex-1 flex-col">
                        <span class="text-ink truncate text-[15px] font-black"
                            >{{ request.nickname
                            }}<span class="text-faint-2"
                                >#{{ request.tag }}</span
                            ></span
                        >
                        <span class="text-faint text-xs font-semibold"
                            >enviado {{ request.sentAgo }}</span
                        >
                    </div>
                    <Form
                        :action="destroy(request.friendshipId)"
                        #default="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            class="text-muted hover:text-ink h-10 cursor-pointer rounded-full px-3 text-sm font-extrabold"
                        >
                            Cancelar
                        </button>
                    </Form>
                </article>
            </section>

            <section class="flex flex-col gap-3">
                <h2
                    v-if="incoming.length || outgoing.length"
                    data-enter
                    class="text-muted flex items-center gap-2 text-[13.5px] font-extrabold"
                >
                    <span class="bg-online size-3 rounded" aria-hidden="true" />
                    Amigos
                </h2>

                <p
                    v-if="friends.length === 0"
                    data-enter
                    class="text-soft rounded-[22px] bg-white/60 px-5 py-6 text-sm font-semibold"
                >
                    Manda um pedido pelo nickname#tag de alguém do grupo.
                </p>

                <ul
                    class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-3"
                >
                    <li
                        v-for="friend in friends"
                        :key="friend.friendshipId"
                        data-enter
                        class="flex min-h-[72px] items-center gap-3.5 rounded-[22px] bg-white p-3 pr-4"
                        :class="cardShadow"
                    >
                        <template
                            v-if="confirmingRemoval === friend.friendshipId"
                        >
                            <div class="flex min-w-0 flex-1 flex-col gap-px">
                                <span class="text-soft text-xs font-bold"
                                    >Desfazer amizade com</span
                                >
                                <span
                                    class="text-ink truncate text-[15.5px] font-black"
                                    >{{ friend.nickname }}?</span
                                >
                            </div>
                            <button
                                type="button"
                                class="text-muted h-9 shrink-0 cursor-pointer rounded-full px-3 text-sm font-extrabold"
                                @click="confirmingRemoval = null"
                            >
                                Não
                            </button>
                            <Form
                                :action="destroy(friend.friendshipId)"
                                #default="{ processing }"
                                @success="confirmingRemoval = null"
                            >
                                <button
                                    type="submit"
                                    :disabled="processing"
                                    class="bg-brand h-9 shrink-0 cursor-pointer rounded-full px-4 text-sm font-extrabold text-white"
                                >
                                    Sim
                                </button>
                            </Form>
                        </template>
                        <template v-else>
                            <Link
                                :href="trainerShow(friend.userId)"
                                class="flex min-w-0 flex-1 items-center gap-3.5"
                            >
                                <div class="relative">
                                    <TrainerAvatar
                                        :sprite-path="friend.avatarSpritePath"
                                    />
                                    <span
                                        v-if="friend.isOnline"
                                        class="bg-online absolute -right-0.5 -bottom-0.5 size-3.5 rounded-full shadow-[0_0_0_2.5px_#fff]"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div
                                    class="flex min-w-0 flex-1 flex-col gap-px"
                                >
                                    <span
                                        class="text-ink truncate text-[15.5px] font-black"
                                    >
                                        {{ friend.nickname
                                        }}<span class="text-faint-2"
                                            >#{{ friend.tag }}</span
                                        >
                                    </span>
                                    <span
                                        class="text-soft text-xs font-semibold"
                                    >
                                        {{ friend.ownedSpecies }} espécies ·
                                        <span
                                            :class="
                                                friend.isOnline
                                                    ? 'text-online'
                                                    : ''
                                            "
                                            >{{
                                                friend.isOnline
                                                    ? 'online'
                                                    : 'offline'
                                            }}</span
                                        >
                                    </span>
                                </div>
                            </Link>
                            <button
                                type="button"
                                :aria-label="`Desfazer amizade com ${friend.nickname}`"
                                class="text-placeholder hover:text-brand-ink flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full transition-colors"
                                @click="confirmingRemoval = friend.friendshipId"
                            >
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                    aria-hidden="true"
                                >
                                    <path d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </template>
                    </li>
                </ul>
            </section>
        </div>
    </GroupScreen>
</template>
