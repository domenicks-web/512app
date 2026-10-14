<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GroupScreen from '@/components/group/GroupScreen.vue';
import TradeCard from '@/components/trade/TradeCard.vue';
import EntryLayout from '@/layouts/EntryLayout.vue';
import GroupLayout from '@/layouts/GroupLayout.vue';
import type { BuilderSpecimen } from '@/lib/trade/types';
import { store } from '@/routes/trades';
import { store as counterStore } from '@/routes/trades/counter';

defineOptions({ layout: [EntryLayout, GroupLayout] });

/**
 * Montador da troca: a mesa (você dá / você recebe, até `limits.perSide`
 * de cada lado) fica no topo; embaixo, a coleção de um dos dois com
 * filtros. Os filtros só escondem o que o servidor mandou. Propor manda os
 * ids; quem valida tudo é o servidor.
 */
const props = defineProps<{
    friend: {
        id: number;
        nickname: string;
        tag: string;
        avatarSpritePath: string | null;
    };
    mine: BuilderSpecimen[];
    theirs: BuilderSpecimen[];
    initial: { give: number[]; receive: number[] };
    counterOf: number | null;
    limits: { perSide: number; message: number };
}>();

const give = ref<number[]>([...props.initial.give]);
const receive = ref<number[]>([...props.initial.receive]);
const message = ref('');
const owner = ref<'mine' | 'theirs'>(
    props.initial.receive.length && !props.initial.give.length
        ? 'mine'
        : 'theirs',
);
type Filter = 'all' | 'duplicate' | 'missing' | 'common';
const filter = ref<Filter>('all');
const errors = ref<Record<string, string>>({});
const sending = ref(false);

const mySpecies = computed(() => new Set(props.mine.map((s) => s.speciesId)));
const theirSpecies = computed(
    () => new Set(props.theirs.map((s) => s.speciesId)),
);
const byId = computed(
    () =>
        new Map([...props.mine, ...props.theirs].map((s) => [s.specimenId, s])),
);

const FILTERS = computed<{ key: Filter; label: string }[]>(() => [
    { key: 'all', label: 'Todos' },
    { key: 'duplicate', label: 'Repetidos' },
    {
        key: 'missing',
        label:
            owner.value === 'theirs'
                ? 'Faltam pra mim'
                : `Faltam pra ${props.friend.nickname}`,
    },
    { key: 'common', label: 'Em comum' },
]);

const visible = computed(() => {
    const list = owner.value === 'mine' ? props.mine : props.theirs;
    const other = owner.value === 'mine' ? theirSpecies.value : mySpecies.value;

    return list.filter((s) => {
        if (filter.value === 'duplicate') return s.duplicate;
        if (filter.value === 'missing') return !other.has(s.speciesId);
        if (filter.value === 'common') return other.has(s.speciesId);

        return true;
    });
});

const side = computed(() => (owner.value === 'mine' ? give : receive));

function toggle(id: number) {
    const target = side.value;
    target.value = target.value.includes(id)
        ? target.value.filter((x) => x !== id)
        : target.value.length < props.limits.perSide
          ? [...target.value, id]
          : target.value;
}

const ready = computed(
    () => give.value.length > 0 && receive.value.length > 0 && !sending.value,
);

function submit() {
    sending.value = true;
    errors.value = {};
    const payload = {
        offered: give.value,
        requested: receive.value,
        message: message.value || null,
    };
    router.post(
        props.counterOf === null
            ? store().url
            : counterStore(props.counterOf).url,
        props.counterOf === null
            ? { ...payload, friend_id: props.friend.id }
            : payload,
        {
            onError: (bag) => (errors.value = bag as Record<string, string>),
            onFinish: () => (sending.value = false),
        },
    );
}
</script>

<template>
    <GroupScreen
        :title="counterOf ? 'Contraproposta' : 'Nova troca'"
        :subtitle="`com ${friend.nickname}`"
    >
        <div class="flex flex-col gap-5">
            <section
                data-enter
                class="sticky top-0 z-[5] flex flex-col gap-3 rounded-[24px] bg-white/90 p-4 shadow-[0_8px_24px_rgba(28,32,38,0.1)] backdrop-blur-md"
            >
                <div class="grid grid-cols-[1fr_auto_1fr] items-start gap-2">
                    <div class="flex flex-col gap-1.5">
                        <span class="text-soft text-[11.5px] font-extrabold"
                            >Você dá {{ give.length }}/{{
                                limits.perSide
                            }}</span
                        >
                        <div class="flex min-h-[78px] flex-wrap gap-1.5">
                            <TradeCard
                                v-for="id in give"
                                :key="id"
                                :specimen="byId.get(id)!"
                                size="sm"
                                @open="give = give.filter((x) => x !== id)"
                            />
                        </div>
                    </div>
                    <span
                        class="text-faint pt-8 text-lg font-black"
                        aria-hidden="true"
                        >⇄</span
                    >
                    <div class="flex flex-col items-end gap-1.5">
                        <span class="text-soft text-[11.5px] font-extrabold"
                            >Você recebe {{ receive.length }}/{{
                                limits.perSide
                            }}</span
                        >
                        <div
                            class="flex min-h-[78px] flex-wrap justify-end gap-1.5"
                        >
                            <TradeCard
                                v-for="id in receive"
                                :key="id"
                                :specimen="byId.get(id)!"
                                size="sm"
                                @open="
                                    receive = receive.filter((x) => x !== id)
                                "
                            />
                        </div>
                    </div>
                </div>
                <input
                    v-model="message"
                    :maxlength="limits.message"
                    placeholder="Mensagem (opcional)"
                    class="text-ink placeholder:text-placeholder bg-field focus:border-brand h-11 rounded-full border-2 border-transparent px-4 text-sm font-semibold outline-none"
                />
                <p
                    v-for="(text, key) in errors"
                    :key="key"
                    role="alert"
                    class="text-brand-ink text-[12.5px] font-extrabold"
                >
                    {{ text }}
                </p>
                <button
                    type="button"
                    :disabled="!ready"
                    class="bg-brand disabled:bg-brand-disabled h-12 cursor-pointer rounded-full text-[15px] font-extrabold text-white shadow-[0_10px_24px_rgba(242,77,77,0.35)] disabled:shadow-none"
                    @click="submit"
                >
                    {{ counterOf ? 'Enviar contraproposta' : 'Propor troca' }}
                </button>
            </section>

            <div data-enter class="flex flex-wrap gap-2">
                <div class="bg-track/60 flex gap-1 rounded-full p-1">
                    <button
                        v-for="o in ['theirs', 'mine'] as const"
                        :key="o"
                        type="button"
                        class="cursor-pointer rounded-full px-4 py-2 text-[13px] font-extrabold"
                        :class="
                            owner === o
                                ? 'text-ink bg-white shadow-[0_1px_3px_rgba(28,32,38,0.1)]'
                                : 'text-soft'
                        "
                        @click="
                            owner = o;
                            filter = 'all';
                        "
                    >
                        {{ o === 'mine' ? 'Meus' : `De ${friend.nickname}` }}
                    </button>
                </div>
                <div
                    class="bg-track/60 flex gap-1 overflow-x-auto rounded-full p-1"
                >
                    <button
                        v-for="f in FILTERS"
                        :key="f.key"
                        type="button"
                        class="cursor-pointer rounded-full px-3 py-2 text-[12.5px] font-extrabold whitespace-nowrap"
                        :class="
                            filter === f.key
                                ? 'text-ink bg-white shadow-[0_1px_3px_rgba(28,32,38,0.1)]'
                                : 'text-soft'
                        "
                        @click="filter = f.key"
                    >
                        {{ f.label }}
                    </button>
                </div>
            </div>

            <ul
                class="grid grid-cols-[repeat(auto-fill,minmax(96px,1fr))] gap-3"
            >
                <li
                    v-for="s in visible"
                    :key="s.specimenId"
                    class="flex justify-center"
                >
                    <TradeCard
                        :specimen="s"
                        :selected="side.value.includes(s.specimenId)"
                        @open="toggle(s.specimenId)"
                    />
                </li>
            </ul>
            <p
                v-if="visible.length === 0"
                class="text-soft py-8 text-center text-sm font-semibold"
            >
                Nada aqui com esse filtro.
            </p>
        </div>
    </GroupScreen>
</template>
