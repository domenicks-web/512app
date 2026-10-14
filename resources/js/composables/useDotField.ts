import { prefersReducedMotion } from '@/lib/motion';

/**
 * Campo de 512 pontos que fica atrás de todo o fluxo de entrada (na
 * abertura, cada ponto é uma pokébola e a grade cobre a tela inteira). Vive num
 * módulo (estado único) porque o canvas mora no layout persistente e
 * sobrevive às visitas do Inertia: login, cadastro, onboarding e home
 * conversam com o mesmo campo. Puramente decorativo.
 */

type Dot = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    tx: number;
    ty: number;
    r: number;
    vr: number;
    tr: number;
    a: number;
    ta: number;
    spawn: number;
};

type Ripple = { x: number; y: number; t: number; s: number };

type FieldMode = 'grid' | 'ambient' | 'point' | 'hide';

const DOT_COUNT = 512;
const COLUMNS = 32;
const ROWS = 16;
/** Na abertura cada ponto é uma pokébola com esse tanto da célula de diâmetro. */
const GRID_FILL = 0.84;
/** Abaixo desse raio a pokébola não se lê: desenha só o ponto. */
const POKEBALL_MIN_RADIUS = 3.5;
const INK = '#2b2f36';
const RED = '#f24d4d';
/** O ponto vermelho, maior, que vira a onda que dá origem à cápsula. */
export const SPECIAL_DOT = 245;

const dots: Dot[] = Array.from({ length: DOT_COUNT }, () => ({
    x: 0,
    y: 0,
    vx: 0,
    vy: 0,
    tx: 0,
    ty: 0,
    r: 0,
    vr: 0,
    tr: 0,
    a: 0,
    ta: 0,
    spawn: 0,
}));

let ripples: Ripple[] = [];
let mode: FieldMode = 'hide';
let pointer: { x: number; y: number } | null = null;
let width = 0;
let height = 0;
let context: CanvasRenderingContext2D | null = null;
let canvas: HTMLCanvasElement | null = null;
let frame = 0;
let onGridCount: ((born: number) => void) | null = null;
let disabled = false;
let gridRadius = 2.1;
let pokeballSprites: { ink: HTMLCanvasElement; red: HTMLCanvasElement } | null =
    null;

/**
 * Pokébola de uma cor só (a mesma máscara do `PokeballMark`: disco menos a
 * faixa e o anel, com o botão no meio), desenhada uma vez num canvas fora
 * da tela e carimbada com `drawImage` em cada ponto.
 */
function pokeballSprite(color: string): HTMLCanvasElement {
    const size = 96;
    const sprite = document.createElement('canvas');
    sprite.width = sprite.height = size;
    const ctx = sprite.getContext('2d');

    if (!ctx) {
        return sprite;
    }

    const c = size / 2;
    const unit = size / 20;
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(c, c, 10 * unit, 0, 6.2832);
    ctx.fill();
    ctx.globalCompositeOperation = 'destination-out';
    ctx.fillRect(0, c - 1.1 * unit, size, 2.2 * unit);
    ctx.beginPath();
    ctx.arc(c, c, 4.6 * unit, 0, 6.2832);
    ctx.fill();
    ctx.globalCompositeOperation = 'source-over';
    ctx.beginPath();
    ctx.arc(c, c, 2.4 * unit, 0, 6.2832);
    ctx.fill();

    return sprite;
}

/** Grade da abertura: a tela inteira, deitada no PC e em pé no celular. */
function gridShape(): { columns: number; rows: number; spacing: number } {
    const isPortrait = height > width;
    const columns = isPortrait ? ROWS : COLUMNS;
    const rows = isPortrait ? COLUMNS : ROWS;

    return { columns, rows, spacing: Math.min(width / columns, height / rows) };
}

function layout(target: 'grid' | 'ambient', snap: boolean) {
    mode = target;
    const grid = gridShape();
    gridRadius = (grid.spacing * GRID_FILL) / 2;

    dots.forEach((dot, i) => {
        if (target === 'grid') {
            const col = i % grid.columns;
            const row = Math.floor(i / grid.columns);
            dot.tx = width / 2 + (col - (grid.columns - 1) / 2) * grid.spacing;
            dot.ty = height / 2 + (row - (grid.rows - 1) / 2) * grid.spacing;

            if (!dot.spawn && dot.tr > 0) {
                dot.tr = gridDotRadius(i);
            }
        } else {
            const col = i % COLUMNS;
            const row = Math.floor(i / COLUMNS);
            dot.tx = ((col + 0.5) * width) / COLUMNS;
            dot.ty = ((row + 0.5) * height) / ROWS;
        }

        if (snap) {
            dot.x = dot.tx;
            dot.y = dot.ty;
            dot.vx = 0;
            dot.vy = 0;
        }
    });
}

function gridDotRadius(index: number): number {
    return index === SPECIAL_DOT ? gridRadius * 1.15 : gridRadius;
}

function resize() {
    if (!canvas || !context) {
        return;
    }

    const dpr = Math.min(2, window.devicePixelRatio || 1);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    context.setTransform(dpr, 0, 0, dpr, 0, 0);

    if (mode === 'grid' || mode === 'ambient') {
        layout(mode, false);
    }
}

function tick(now: number) {
    frame = requestAnimationFrame(tick);

    if (!context) {
        return;
    }

    context.clearRect(0, 0, width, height);

    if (disabled) {
        return;
    }

    const isAmbient = mode === 'ambient';
    ripples = ripples.filter((ripple) => now - ripple.t < 1700);
    let born = 0;

    for (let i = 0; i < DOT_COUNT; i++) {
        const dot = dots[i];

        if (dot.spawn) {
            if (now >= dot.spawn) {
                dot.spawn = 0;
                dot.tr = gridDotRadius(i);
                dot.ta = i === SPECIAL_DOT ? 1 : 0.55;
                dot.vr = 1.4;
            }
        } else if (mode === 'grid') {
            born++;
        }

        dot.vx = (dot.vx + (dot.tx - dot.x) * 0.05) * 0.86;
        dot.x += dot.vx;
        dot.vy = (dot.vy + (dot.ty - dot.y) * 0.05) * 0.86;
        dot.y += dot.vy;
        dot.vr = (dot.vr + (dot.tr - dot.r) * 0.2) * 0.74;
        dot.r += dot.vr;
        dot.a += (dot.ta - dot.a) * 0.1;

        let x = dot.x;
        let y = dot.y;
        let r = dot.r;
        let heat = i === SPECIAL_DOT ? 1 : 0;

        if (isAmbient && pointer) {
            const dx = x - pointer.x;
            const dy = y - pointer.y;
            const distance = Math.hypot(dx, dy) || 1;

            if (distance < 150) {
                const force = 1 - distance / 150;
                x += (dx / distance) * force * 12;
                y += (dy / distance) * force * 12;
                r += force * force * 2.6;
            }
        }

        for (const ripple of ripples) {
            const age = now - ripple.t;
            const band = Math.abs(
                Math.hypot(dot.x - ripple.x, dot.y - ripple.y) - age * 0.85,
            );

            if (band < 50) {
                const force = (1 - band / 50) * Math.max(0, 1 - age / 1700);
                r += force * 3 * ripple.s;
                heat = Math.max(heat, force);
            }
        }

        const alpha = Math.min(1, dot.a + heat * 0.55 * (dot.a > 0.05 ? 1 : 0));

        if (r < 0.1 || alpha < 0.01) {
            continue;
        }

        if (pokeballSprites && r >= POKEBALL_MIN_RADIUS) {
            // Cinza → vermelho onde a onda passa, como a cor do ponto.
            const size = r * 2;
            context.globalAlpha = alpha * (1 - heat);
            context.drawImage(pokeballSprites.ink, x - r, y - r, size, size);
            context.globalAlpha = alpha * heat;
            context.drawImage(pokeballSprites.red, x - r, y - r, size, size);
            context.globalAlpha = 1;
            continue;
        }

        context.fillStyle = `rgba(${Math.round(43 + 199 * heat)},${Math.round(47 + 30 * heat)},${Math.round(54 + 23 * heat)},${alpha})`;
        context.beginPath();
        context.arc(x, y, r, 0, 6.2832);
        context.fill();
    }

    if (mode === 'grid' && onGridCount) {
        onGridCount(born);
    }
}

const handlePointerMove = (event: PointerEvent) => {
    pointer = { x: event.clientX, y: event.clientY };
};
const handlePointerLeave = () => {
    pointer = null;
};

export function mountDotField(element: HTMLCanvasElement): () => void {
    canvas = element;
    context = element.getContext('2d');
    disabled = prefersReducedMotion();
    pokeballSprites ??= { ink: pokeballSprite(INK), red: pokeballSprite(RED) };
    resize();
    window.addEventListener('pointermove', handlePointerMove);
    document.addEventListener('pointerleave', handlePointerLeave);
    window.addEventListener('resize', resize);
    frame = requestAnimationFrame(tick);

    return () => {
        cancelAnimationFrame(frame);
        window.removeEventListener('pointermove', handlePointerMove);
        document.removeEventListener('pointerleave', handlePointerLeave);
        window.removeEventListener('resize', resize);
        canvas = null;
        context = null;
    };
}

export function useDotField() {
    /** Acende as 512 pokébolas em ordem aleatória (curva rank^0.8) em 1.4s, cobrindo a tela. */
    function grid(countCallback: (born: number) => void) {
        onGridCount = countCallback;
        layout('grid', true);
        const order = dots.map((_, i) => i).filter((i) => i !== SPECIAL_DOT);

        for (let i = order.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [order[i], order[j]] = [order[j], order[i]];
        }

        order.push(SPECIAL_DOT);
        const now = performance.now();
        order.forEach((index, rank) => {
            const dot = dots[index];
            dot.r = dot.tr = dot.a = dot.ta = 0;
            dot.spawn = now + Math.pow(rank / 511, 0.8) * 1400;
        });
    }

    function ambient(snap = false) {
        onGridCount = null;
        layout('ambient', snap);
        dots.forEach((dot, i) => {
            dot.spawn = 0;
            dot.tr = i === SPECIAL_DOT ? 2.4 : 1.5;
            dot.ta = i === SPECIAL_DOT ? 0.7 : 0.2;

            if (snap) {
                dot.r = dot.tr;
                dot.a = dot.ta;
            }
        });
    }

    /** Todos os pontos convergem pra (x, y), com redemoinho opcional. */
    function converge(
        x: number,
        y: number,
        options: { swirl?: number; specialRadius?: number } = {},
    ) {
        mode = 'point';
        dots.forEach((dot, i) => {
            dot.tx = x;
            dot.ty = y;

            if (options.swirl) {
                dot.vx += -(dot.y - y) * options.swirl;
                dot.vy += (dot.x - x) * options.swirl;
            }

            dot.tr = i === SPECIAL_DOT ? (options.specialRadius ?? 0.8) : 0.8;
        });
    }

    function fadeAllExceptSpecial() {
        dots.forEach((dot, i) => {
            if (i !== SPECIAL_DOT) {
                dot.ta = 0;
            }
        });
    }

    function popSpecial() {
        const dot = dots[SPECIAL_DOT];
        dot.tr = 0;
        dot.ta = 0;
        ripple(width / 2, height / 2, 1.2);
    }

    function hide() {
        mode = 'hide';
        dots.forEach((dot) => {
            dot.ta = 0;
            dot.tr = 0;
        });
    }

    function fade() {
        dots.forEach((dot) => {
            dot.ta = 0;
        });
    }

    function ripple(x: number, y: number, strength = 1) {
        ripples.push({ x, y, t: performance.now(), s: strength });

        if (ripples.length > 8) {
            ripples.shift();
        }
    }

    /** Onda vermelha saindo do caret de um input, a cada tecla. */
    function rippleFromInput(input: HTMLInputElement, textWidth?: number) {
        const rect = input.getBoundingClientRect();
        let measured = textWidth;

        if (measured === undefined) {
            const measure = document.createElement('canvas').getContext('2d');

            if (measure) {
                measure.font = '600 15px Inter';
                measured = measure.measureText(input.value).width;
            }
        }

        ripple(
            Math.min(rect.right - 22, rect.left + 22 + (measured ?? 0)),
            rect.top + rect.height / 2,
            0.75,
        );
    }

    return {
        grid,
        ambient,
        converge,
        fadeAllExceptSpecial,
        popSpecial,
        hide,
        fade,
        ripple,
        rippleFromInput,
        mode: () => mode,
        center: () => ({ x: width / 2, y: height / 2 }),
    };
}
