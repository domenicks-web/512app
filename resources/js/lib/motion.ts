/**
 * Ferramentas de animação do fluxo de entrada (loading → login → cadastro →
 * home). Tudo em Web Animations API, sem lib: as molas viram uma curva
 * `linear()` gerada por simulação física (rigidez k, amortecimento c), com
 * fallback pra cubic-bezier em navegador sem suporte a `linear()`.
 */

const springCache = new Map<string, string>();

const supportsLinearEasing =
    typeof CSS !== 'undefined' &&
    typeof CSS.supports === 'function' &&
    CSS.supports('animation-timing-function', 'linear(0, 1)');

export function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

export function spring(stiffness: number, damping: number): string {
    const key = `${stiffness}_${damping}`;
    const cached = springCache.get(key);

    if (cached) {
        return cached;
    }

    if (!supportsLinearEasing) {
        springCache.set(key, 'cubic-bezier(.34,1.5,.64,1)');

        return 'cubic-bezier(.34,1.5,.64,1)';
    }

    let position = 0;
    let velocity = 0;
    const samples = 50;
    const substeps = 4;
    const dt = 1.1 / samples / substeps;
    const points = [0];

    for (let i = 1; i <= samples; i++) {
        for (let j = 0; j < substeps; j++) {
            const acceleration =
                -stiffness * (position - 1) - damping * velocity;
            velocity += acceleration * dt;
            position += velocity * dt;
        }

        points.push(Number(position.toFixed(4)));
    }

    points[points.length - 1] = 1;
    const easing = `linear(${points.join(',')})`;
    springCache.set(key, easing);

    return easing;
}

export const springs = {
    slide: () => spring(150, 19),
    card: () => spring(170, 19),
    digits: () => spring(220, 13),
    pop: () => spring(300, 13),
    bounce: () => spring(320, 13),
    capsuleClose: () => spring(380, 16),
};

type AnimateOptions = {
    delay?: number;
    easing?: string;
    fill?: FillMode;
    iterations?: number;
};

/**
 * Wrapper fino do `element.animate`. Com `prefers-reduced-motion`, troca
 * qualquer movimento por um fade curto (mantém só o último quadro).
 */
export function animate(
    element: Element | null | undefined,
    keyframes: Keyframe[],
    duration: number,
    options: AnimateOptions = {},
): Animation | null {
    if (!element || typeof element.animate !== 'function') {
        return null;
    }

    if (prefersReducedMotion()) {
        if (options.iterations === Infinity) {
            return null;
        }

        const last = keyframes[keyframes.length - 1] ?? {};

        return element.animate([{ ...last, opacity: 0 }, { ...last }], {
            duration: Math.min(160, duration),
            fill: options.fill ?? 'backwards',
        });
    }

    return element.animate(keyframes, {
        duration,
        delay: options.delay ?? 0,
        easing: options.easing ?? 'cubic-bezier(.4,0,.2,1)',
        fill: options.fill ?? 'backwards',
        iterations: options.iterations ?? 1,
    });
}

export function finished(animation: Animation | null): Promise<void> {
    return animation
        ? animation.finished.then(
              () => undefined,
              () => undefined,
          )
        : Promise.resolve();
}

export function wait(ms: number): Promise<void> {
    return new Promise((resolve) =>
        window.setTimeout(resolve, prefersReducedMotion() ? 0 : ms),
    );
}

export function shake(element: Element | null | undefined): void {
    animate(
        element,
        [
            { transform: 'none' },
            { transform: 'translateX(-10px) rotate(-1.5deg)' },
            { transform: 'translateX(8px) rotate(1.2deg)' },
            { transform: 'translateX(-5px)' },
            { transform: 'translateX(3px)' },
            { transform: 'none' },
        ],
        480,
        { easing: 'ease-out', fill: 'none' },
    );
}

/**
 * Contador de geração: cada sequência guarda o número com que começou e
 * para assim que ele muda (tela trocou, componente desmontou).
 */
export function createGeneration() {
    let current = 0;

    return {
        next: () => ++current,
        isCurrent: (generation: number) => generation === current,
        invalidate: () => {
            current++;
        },
    };
}

/**
 * Posiciona `element` pra encolher até o retângulo de `target` (ou nascer
 * dele): devolve o transform de origem em coordenadas de tela.
 */
export function transformToRect(element: HTMLElement, target: Element): string {
    const from = element.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);
    const sx = to.width / element.offsetWidth;
    const sy = to.height / element.offsetHeight;

    return `translate(${dx}px,${dy}px) scale(${sx},${sy})`;
}
