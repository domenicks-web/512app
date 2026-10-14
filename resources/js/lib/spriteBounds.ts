/** Retângulo do que é desenhado de fato no sprite (pixels não transparentes). */
export type SpriteBounds = {
    x: number;
    y: number;
    width: number;
    height: number;
    naturalWidth: number;
    naturalHeight: number;
};

const ALPHA_THRESHOLD = 16;
const cache = new Map<string, Promise<SpriteBounds | null>>();

/**
 * Mede (uma vez por URL) onde o Pokémon está dentro do PNG do sprite. Os
 * sprites são todos 96×96, mas um Pikachu ocupa bem menos que um Charizard;
 * com a medida dá pra encaixar todos com a mesma margem. Devolve `null` se
 * não der pra ler a imagem.
 */
export function spriteBounds(src: string): Promise<SpriteBounds | null> {
    const cached = cache.get(src);

    if (cached) {
        return cached;
    }

    const measuring = new Promise<SpriteBounds | null>((resolve) => {
        const image = new Image();
        image.decoding = 'async';
        image.onerror = () => resolve(null);
        image.onload = () => {
            const { naturalWidth: width, naturalHeight: height } = image;
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d', {
                willReadFrequently: true,
            });

            if (!context || width === 0 || height === 0) {
                resolve(null);

                return;
            }

            context.drawImage(image, 0, 0);
            const { data } = context.getImageData(0, 0, width, height);
            let minX = width;
            let minY = height;
            let maxX = -1;
            let maxY = -1;

            for (let y = 0; y < height; y++) {
                for (let x = 0; x < width; x++) {
                    if (data[(y * width + x) * 4 + 3] > ALPHA_THRESHOLD) {
                        minX = Math.min(minX, x);
                        minY = Math.min(minY, y);
                        maxX = Math.max(maxX, x);
                        maxY = Math.max(maxY, y);
                    }
                }
            }

            resolve(
                maxX < 0
                    ? null
                    : {
                          x: minX,
                          y: minY,
                          width: maxX - minX + 1,
                          height: maxY - minY + 1,
                          naturalWidth: width,
                          naturalHeight: height,
                      },
            );
        };
        image.src = src;
    });

    cache.set(src, measuring);

    return measuring;
}
