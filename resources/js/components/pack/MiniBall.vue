<script setup lang="ts">
/** Pokébola desenhada em CSS (a do protótipo), usada no centro e nos slots. */
withDefaults(
    defineProps<{
        shine?: boolean;
        band?: number;
        button?: number;
        keys?: { shake?: string; top?: string; btn?: string };
    }>(),
    {
        shine: false,
        band: 18,
        button: 36,
        keys: () => ({}),
    },
);
</script>

<template>
    <div :data-k="keys.shake" class="absolute inset-0 origin-[50%_90%]">
        <div
            :data-k="keys.top"
            class="absolute top-0 left-0 h-1/2 w-full origin-[10%_100%] overflow-hidden rounded-t-full bg-[#f24d4d]"
        >
            <div
                v-if="shine"
                class="absolute inset-0 bg-[radial-gradient(140%_120%_at_72%_115%,rgba(110,10,20,0.28),rgba(110,10,20,0)_60%)]"
            />
            <div
                class="absolute top-[27%] left-[18%] h-[15%] w-1/4 -rotate-30 rounded-full bg-[#ffd9d9] opacity-75"
            />
        </div>
        <div
            class="absolute top-1/2 left-0 z-[2] h-1/2 w-full rounded-b-full bg-[#f4f5f7]"
        >
            <div
                v-if="shine"
                class="absolute inset-0 rounded-[inherit] bg-[radial-gradient(130%_120%_at_60%_125%,rgba(28,31,38,0.26),rgba(28,31,38,0)_62%)]"
            />
            <div
                class="absolute right-0 left-0 bg-[#434a53]"
                :style="{ top: `-${band / 2}%`, height: `${band}%` }"
            />
            <div
                class="absolute top-0 left-1/2 flex aspect-square -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-[#434a53]"
                :style="{ width: `${button}%` }"
            >
                <div
                    :data-k="keys.btn"
                    class="h-[54%] w-[54%] rounded-full bg-[#e4e7eb]"
                />
            </div>
        </div>
    </div>
</template>
