import type { Directive } from 'vue';
import { spriteBounds } from '@/lib/spriteBounds';
import { integerScale } from './meta';

/**
 * `v-pixel-fit="{ cap, maxH, maxW?, min? }"`: dimensiona o sprite numa
 * escala inteira (nunca fracionária, `image-rendering: pixelated`) e
 * centraliza na horizontal pelo desenho, não pelo quadro: o GIF do Mewtwo,
 * por exemplo, tem o quadro largo pro rabo esticado e o corpo fica à
 * esquerda. Se o GIF animado falhar, cai no PNG de `data-fallback`.
 */
export type PixelFit = {
    cap: number;
    maxH: number;
    maxW?: number;
    min?: number;
};

type FitElement = HTMLImageElement & {
    __fit?: PixelFit;
    __onResize?: () => void;
};

function fit(img: FitElement): void {
    const options = img.__fit;
    const naturalWidth = img.naturalWidth;
    const naturalHeight = img.naturalHeight;

    if (!options || !naturalWidth || !naturalHeight) {
        return;
    }

    const maxW = Math.min(
        options.maxW ?? (img.parentElement?.clientWidth || 100),
        innerWidth * 0.8,
    );
    let k = integerScale(
        naturalWidth,
        naturalHeight,
        options.cap,
        maxW,
        options.maxH,
    );

    if (options.min && k < options.min && options.maxH / naturalHeight >= 1.5) {
        k = options.min;
    }

    img.style.width = `${naturalWidth * k}px`;
    img.style.height = `${naturalHeight * k}px`;
    center(img, k);
}

/**
 * Desloca o sprite até o meio do desenho (medido no primeiro quadro, a
 * pose parada) cair no meio da caixa.
 */
function center(img: FitElement, scale: number): void {
    const src = img.currentSrc || img.src;

    void spriteBounds(src).then((bounds) => {
        if (!bounds || (img.currentSrc || img.src) !== src) {
            return;
        }

        const offset =
            (bounds.naturalWidth / 2 - (bounds.x + bounds.width / 2)) * scale;
        img.style.translate = `${Math.round(offset)}px 0`;
    });
}

function onLoad(event: Event): void {
    fit(event.target as FitElement);
}

function onError(event: Event): void {
    const img = event.target as HTMLImageElement;
    const fallback = img.dataset.fallback;

    if (fallback && !img.src.endsWith(fallback)) {
        img.src = fallback;
    }
}

export const vPixelFit: Directive<FitElement, PixelFit> = {
    mounted(img, binding) {
        img.__fit = binding.value;
        img.__onResize = () => fit(img);
        img.addEventListener('load', onLoad);
        img.addEventListener('error', onError);
        window.addEventListener('resize', img.__onResize);

        if (img.complete) {
            fit(img);
        }
    },
    updated(img, binding) {
        img.__fit = binding.value;
        fit(img);
    },
    unmounted(img) {
        img.removeEventListener('load', onLoad);
        img.removeEventListener('error', onError);

        if (img.__onResize) {
            window.removeEventListener('resize', img.__onResize);
        }
    },
};
