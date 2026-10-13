<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Chip de vidro com o avatar, o nickname e a tag do treinador, que vai se
 * preenchendo durante o onboarding. A tag gira como caça-níquel: quatro
 * colunas de 0 a 9, cada uma um pouco mais lenta que a anterior. Ela só
 * gira quando chega uma tag nova do servidor — o cliente nunca sorteia.
 */
const props = defineProps<{
    nickname: string;
    tag: string | null;
    avatarSpritePath: string | null;
}>();

const root = ref<HTMLElement | null>(null);
const avatar = ref<HTMLElement | null>(null);

const digits = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];

const columns = computed(() =>
    (props.tag ?? '0000').split('').map((digit, index) => ({
        transform: `translateY(-${Number(digit) * 1.25}em)`,
        transition: `transform ${0.55 + index * 0.13}s cubic-bezier(.34,1.56,.64,1)`,
    })),
);

defineExpose({ root, avatar });
</script>

<template>
    <div
        ref="root"
        class="flex h-[54px] w-full max-w-[400px] shrink-0 items-center gap-3 rounded-full bg-white/80 px-[9px] shadow-[0_0_0_1px_rgba(28,32,38,0.06),0_8px_22px_rgba(28,32,38,0.07)] backdrop-blur-[10px]"
    >
        <div
            ref="avatar"
            class="flex h-[38px] w-[38px] shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-[0_0_0_2px_var(--color-line)]"
        >
            <img
                v-if="avatarSpritePath"
                :src="`/storage/${avatarSpritePath}`"
                alt=""
                width="48"
                height="48"
                class="pixelated h-12 w-12 max-w-none"
            />
            <div
                v-else
                class="h-[22px] w-[22px] rounded-full border-2 border-dashed border-[#c8ccd2]"
            />
        </div>
        <div
            class="flex items-baseline text-[15.5px] leading-[1.25] font-black"
        >
            <span :class="nickname ? 'text-ink' : 'text-faint-2'">
                {{ nickname || 'Treinador' }}
            </span>
            <span
                class="text-faint-2 flex"
                :aria-label="tag ? `#${tag}` : undefined"
            >
                #
                <span
                    v-for="(column, index) in columns"
                    :key="index"
                    class="inline-block h-[1.25em] overflow-hidden tabular-nums"
                    aria-hidden="true"
                >
                    <span class="flex flex-col" :style="column">
                        <span
                            v-for="digit in digits"
                            :key="digit"
                            class="block h-[1.25em]"
                        >
                            {{ digit }}
                        </span>
                    </span>
                </span>
            </span>
        </div>
    </div>
</template>
