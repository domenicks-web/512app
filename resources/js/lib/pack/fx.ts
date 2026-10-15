/**
 * Canvas de efeitos da abertura: poeira subindo, partículas de explosão,
 * faíscas puxadas pra bola durante a carga e estrelinhas de shiny. Porte
 * do `loop`/`emit` do protótipo. Puramente decorativo (o aleatório daqui
 * não decide nada do jogo).
 */
type Particle = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    life: number;
    max: number;
    r: number;
    col: string;
    g: number;
    drag: number;
    shape: 'dot' | 'star' | 'spark';
    free: boolean;
    rot: number;
    target?: { x: number; y: number };
};

type Dust = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    r: number;
    a: number;
    tw: number;
};

export type EmitOptions = {
    cols?: string[];
    ang?: number;
    spread?: number;
    sp?: [number, number];
    life?: [number, number];
    r?: [number, number];
    g?: number;
    drag?: number;
    shape?: Particle['shape'];
    free?: boolean;
    jit?: number;
};

const rand = (a: number, b: number): number => a + Math.random() * (b - a);

export class FxCanvas {
    /** Congela tudo que não é `free` (durante a cerimônia). */
    frozen = false;
    /** Estrelinhas em volta de um elemento (sprite da cerimônia). */
    twinkle: { el: Element; col: string } | null = null;
    /** Sprites de shiny nos slots que soltam brilho de vez em quando. */
    sparkTargets: (() => Element | null)[] = [];
    /** Chamado a cada quadro antes de desenhar (carga da bola no idle). */
    onFrame: ((dt: number) => void) | null = null;

    private particles: Particle[] = [];
    private dust: Dust[] = [];
    private ctx: CanvasRenderingContext2D | null = null;
    private raf = 0;
    private last = 0;
    private readonly scale: number;

    constructor(
        private readonly canvas: HTMLCanvasElement,
        reduced: boolean,
    ) {
        this.ctx = canvas.getContext('2d');
        this.scale = reduced ? 0.35 : 1;
        this.resize();
        this.dust = Array.from({ length: Math.round(56 * this.scale) }, () => ({
            x: rand(0, innerWidth),
            y: rand(0, innerHeight),
            vx: rand(-4, 4),
            vy: rand(-12, -3),
            r: rand(0.6, 1.8),
            a: rand(0.06, 0.26),
            tw: rand(0, 6),
        }));
        window.addEventListener('resize', this.resize);
        this.last = performance.now();
        this.raf = requestAnimationFrame(this.loop);
    }

    destroy(): void {
        cancelAnimationFrame(this.raf);
        window.removeEventListener('resize', this.resize);
    }

    clear(): void {
        this.particles = [];
    }

    dropAttracted(): void {
        this.particles = this.particles.filter((p) => !p.target);
    }

    private resize = (): void => {
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        this.canvas.width = innerWidth * dpr;
        this.canvas.height = innerHeight * dpr;
        this.ctx?.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    emit(x: number, y: number, n: number, o: EmitOptions = {}): void {
        const cols = o.cols ?? ['#ffffff'];
        const count = Math.max(1, Math.round(n * this.scale));

        for (let k = 0; k < count; k++) {
            const angle =
                (o.ang ?? 0) +
                (o.spread == null
                    ? Math.random() * Math.PI * 2
                    : (Math.random() - 0.5) * o.spread);
            const speed = rand(o.sp?.[0] ?? 2, o.sp?.[1] ?? 8);
            const life = rand(o.life?.[0] ?? 0.5, o.life?.[1] ?? 1.1);
            const jitter = o.jit ?? 0;
            this.particles.push({
                x: x + rand(-jitter, jitter),
                y: y + rand(-jitter, jitter),
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                life,
                max: life,
                r: rand(o.r?.[0] ?? 1.5, o.r?.[1] ?? 4),
                col: cols[k % cols.length],
                g: o.g ?? 0.12,
                drag: o.drag ?? 0.95,
                shape: o.shape ?? 'dot',
                free: !!o.free,
                rot: rand(0, 6),
            });
        }
    }

    /** Faísca que nasce longe e é puxada pro centro da bola (carga). */
    attract(
        cx: number,
        cy: number,
        cols: [string, string] = ['#f24d4d', '#ffd9d9'],
    ): void {
        const angle = rand(0, Math.PI * 2);
        const radius = rand(170, 340);
        this.particles.push({
            x: cx + Math.cos(angle) * radius,
            y: cy + Math.sin(angle) * radius,
            vx: 0,
            vy: 0,
            life: 1.2,
            max: 1.2,
            r: rand(1, 2.6),
            col: Math.random() < 0.5 ? cols[0] : cols[1],
            g: 0,
            drag: 1,
            shape: 'spark',
            free: false,
            rot: 0,
            target: { x: cx, y: cy },
        });
    }

    private star(
        c: CanvasRenderingContext2D,
        x: number,
        y: number,
        r: number,
        rot: number,
    ): void {
        c.save();
        c.translate(x, y);
        c.rotate(rot);
        c.beginPath();

        for (let k = 0; k < 8; k++) {
            const rr = k % 2 ? r * 0.28 : r;
            const an = (k * Math.PI) / 4;
            c.lineTo(Math.cos(an) * rr, Math.sin(an) * rr);
        }

        c.closePath();
        c.fill();
        c.restore();
    }

    private loop = (ts: number): void => {
        this.raf = requestAnimationFrame(this.loop);
        const dt = Math.min(0.05, (ts - this.last) / 1000);
        this.last = ts;
        this.onFrame?.(dt);

        const c = this.ctx;

        if (!c) {
            return;
        }

        const W0 = innerWidth;
        const H0 = innerHeight;
        const f60 = dt * 60;
        c.clearRect(0, 0, W0, H0);

        for (const d of this.dust) {
            if (!this.frozen) {
                d.x += d.vx * dt;
                d.y += d.vy * dt;
                d.tw += dt * 1.5;

                if (d.y < -10) {
                    d.y = H0 + 10;
                    d.x = rand(0, W0);
                }
            }

            c.globalAlpha = d.a * (0.6 + 0.4 * Math.sin(d.tw));
            c.fillStyle = '#ffffff';
            c.beginPath();
            c.arc(d.x, d.y, d.r, 0, 6.283);
            c.fill();
        }

        if (this.twinkle && Math.random() < dt * 14 * this.scale) {
            const r = this.twinkle.el.getBoundingClientRect();

            if (r.width) {
                this.particles.push({
                    x: rand(r.left - 20, r.right + 20),
                    y: rand(r.top - 10, r.bottom),
                    vx: 0,
                    vy: -0.25,
                    life: 0.8,
                    max: 0.8,
                    r: rand(3, 7),
                    col: this.twinkle.col,
                    g: 0,
                    drag: 1,
                    shape: 'star',
                    free: true,
                    rot: rand(0, 1),
                });
            }
        }

        if (this.sparkTargets.length && Math.random() < dt * 3) {
            const el =
                this.sparkTargets[
                    Math.floor(Math.random() * this.sparkTargets.length)
                ]();
            const r = el?.getBoundingClientRect();

            if (r?.width) {
                this.particles.push({
                    x: rand(r.left - 6, r.right + 6),
                    y: rand(r.top, r.bottom),
                    vx: 0,
                    vy: -0.2,
                    life: 0.7,
                    max: 0.7,
                    r: rand(2, 4),
                    col: '#4DD9E8',
                    g: 0,
                    drag: 1,
                    shape: 'star',
                    free: false,
                    rot: 0,
                });
            }
        }

        const ps = this.particles;

        for (let k = ps.length - 1; k >= 0; k--) {
            const p = ps[k];

            if (!this.frozen || p.free) {
                if (p.target) {
                    const dx = p.target.x - p.x;
                    const dy = p.target.y - p.y;
                    const dd = Math.hypot(dx, dy) || 1;
                    p.vx += (dx / dd) * 0.9 * f60;
                    p.vy += (dy / dd) * 0.9 * f60;
                    p.vx *= Math.pow(0.9, f60);
                    p.vy *= Math.pow(0.9, f60);

                    if (dd < 30) {
                        p.life = 0;
                    }
                } else {
                    p.vx *= Math.pow(p.drag, f60);
                    p.vy = p.vy * Math.pow(p.drag, f60) + p.g * f60;
                }

                p.x += p.vx * f60;
                p.y += p.vy * f60;
                p.life -= dt;
                p.rot += dt * 2;
            }

            if (p.life <= 0) {
                ps.splice(k, 1);
                continue;
            }

            c.globalAlpha = Math.min(1, (p.life / p.max) * 1.4);
            c.fillStyle = p.col;
            c.strokeStyle = p.col;

            if (p.shape === 'star') {
                this.star(
                    c,
                    p.x,
                    p.y,
                    p.r * (0.4 + 0.6 * Math.sin((Math.PI * p.life) / p.max)),
                    p.rot,
                );
            } else if (p.shape === 'spark') {
                c.lineWidth = p.r * 0.6;
                c.lineCap = 'round';
                c.beginPath();
                c.moveTo(p.x, p.y);
                c.lineTo(p.x - p.vx * 2.6, p.y - p.vy * 2.6);
                c.stroke();
            } else {
                c.beginPath();
                c.arc(p.x, p.y, p.r, 0, 6.283);
                c.fill();
            }
        }

        c.globalAlpha = 1;
    };
}
