<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { spriteBounds } from '@/lib/spriteBounds';
import type { SpriteBounds } from '@/lib/spriteBounds';

/**
 * Sprite encaixado num quadrado de `size` px com a mesma margem (`padding`)
 * pra qualquer Pokémon: mede a área desenhada do PNG e escala só ela pra
 * caber, centralizada. Ampliando, fica pixelado (pixel art nítida);
 * reduzindo, suaviza (reduzir pixel art no modo pixelado come detalhe).
 * Até medir, aparece transparente pra não pular de tamanho.
 */
const props = withDefaults(
    defineProps<{
        src: string;
        size: number;
        padding?: number;
        alt?: string;
    }>(),
    { padding: 6, alt: '' },
);

const bounds = ref<SpriteBounds | null>(null);
const isMeasured = ref(false);

async function measure() {
    isMeasured.value = false;
    bounds.value = await spriteBounds(props.src);
    isMeasured.value = true;
}

onMounted(measure);
watch(() => props.src, measure);

const layout = computed(() => {
    const inner = props.size - props.padding * 2;
    const b = bounds.value;

    if (!b) {
        return {
            width: inner,
            height: inner,
            left: props.padding,
            top: props.padding,
            scale: 1,
        };
    }

    const scale = Math.min(inner / b.width, inner / b.height);

    return {
        width: b.naturalWidth * scale,
        height: b.naturalHeight * scale,
        left: props.padding + (inner - b.width * scale) / 2 - b.x * scale,
        top: props.padding + (inner - b.height * scale) / 2 - b.y * scale,
        scale,
    };
});
</script>

<template>
    <span
        class="relative block shrink-0 overflow-hidden"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <img
            :src="src"
            :alt="alt"
            draggable="false"
            class="absolute max-w-none transition-opacity duration-150"
            :class="[
                layout.scale >= 1 ? 'pixelated' : '',
                isMeasured ? 'opacity-100' : 'opacity-0',
            ]"
            :style="{
                width: `${layout.width}px`,
                height: `${layout.height}px`,
                left: `${layout.left}px`,
                top: `${layout.top}px`,
            }"
        />
    </span>
</template>
