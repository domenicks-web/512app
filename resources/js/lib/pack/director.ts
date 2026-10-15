import { nextTick } from 'vue';
import { FxCanvas } from './fx';
import { CYAN, glowOf, hasCeremony, rgba, spriteOf } from './meta';
import type { Sfx } from './sfx';
import type { PackSpecimen, Receipt } from './types';

/**
 * Encena a abertura do pack sobre o DOM da página (`data-k` e
 * `data-slot`), com WAAPI e os mesmos timings/curvas do protótipo
 * `512 Abertura`. Nunca decide nada: o pack é sorteado no servidor na
 * explosão (`openPack`) e cada bola só descobre o conteúdo quando o
 * jogador clica nela (`reveal`).
 *
 * Diferenças obrigatórias do protótipo (ABERTURA.md):
 * - as bolas não abrem sozinhas: o jogador escolhe qual e quando;
 * - cerimônia trava as outras bolas até o "toque para continuar";
 * - uma bola por vez, no toque (o "Abrir todas" saiu: a graça é ver uma a uma).
 */
export type SlotState = {
    slot: number;
    specimen: PackSpecimen | null;
    pending: boolean;
};

export type PackSceneState = {
    phase: 'idle' | 'run' | 'balls' | 'summary';
    slots: SlotState[];
    openingId: number | null;
    ceremony: PackSpecimen | null;
    locked: boolean;
    receipt: Receipt | null;
    nextPackAt: string | null;
    error: string | null;
};

export type PackApi = {
    openPack(): Promise<{ openingId: number; nextPackAt: string | null }>;
    reveal(
        openingId: number,
        slot: number,
    ): Promise<{
        specimen: PackSpecimen;
        isComplete: boolean;
        receipt: Receipt | null;
    }>;
};

type AnimateOptions = {
    d?: number;
    e?: string;
    fill?: FillMode;
    it?: number;
    dir?: PlaybackDirection;
};

class Aborted extends Error {}

const ABORT = new Aborted();

export class PackDirector {
    private readonly fx: FxCanvas;
    private token = 0;
    private charge = 0;
    private charging = false;
    private phase = 0;
    private lastTick = 0;
    private idleTime = 0;
    private tilt = 0;
    private pointer: { x: number; y: number } | null = null;
    private tapResolve: (() => void) | null = null;
    private wobbles = new Map<number, Animation>();

    constructor(
        private readonly root: HTMLElement,
        canvas: HTMLCanvasElement,
        private readonly state: PackSceneState,
        private readonly api: PackApi,
        private readonly sfx: Sfx,
        private readonly reduced: boolean,
    ) {
        this.fx = new FxCanvas(canvas, reduced);
        this.fx.onFrame = (dt) => {
            if (this.state.phase === 'idle') {
                this.idleFrame(dt);
            }
        };
        window.addEventListener('pointermove', this.onPointerMove);
        window.addEventListener('pointerup', this.onPointerUp);
        window.addEventListener('pointercancel', this.onPointerUp);
        window.addEventListener('keydown', this.onKeyDown);
        window.addEventListener('keyup', this.onKeyUp);
        const rays = this.$('rays');

        if (rays) {
            rays.style.maskImage =
                'radial-gradient(circle, #000 0%, rgba(0,0,0,0) 60%)';
        }
    }

    destroy(): void {
        this.token++;
        this.fx.destroy();
        this.sfx.rumble(0);
        window.removeEventListener('pointermove', this.onPointerMove);
        window.removeEventListener('pointerup', this.onPointerUp);
        window.removeEventListener('pointercancel', this.onPointerUp);
        window.removeEventListener('keydown', this.onKeyDown);
        window.removeEventListener('keyup', this.onKeyUp);
    }

    // ── helpers ────────────────────────────────────────────────────────

    private $(key: string): HTMLElement | null {
        return this.root.querySelector<HTMLElement>(`[data-k="${key}"]`);
    }

    private slot(index: number, key?: string): HTMLElement | null {
        const slot = this.root.querySelector<HTMLElement>(
            `[data-slot="${index}"]`,
        );

        return slot && key
            ? slot.querySelector<HTMLElement>(`[data-k="${key}"]`)
            : slot;
    }

    /** Dispara a animação e segue (a maioria, como no protótipo). */
    private a(
        el: Element | null,
        keyframes: Keyframe[],
        duration: number,
        o: AnimateOptions = {},
    ): void {
        void this.aw(el, keyframes, duration, o);
    }

    /** Anima e espera terminar. */
    private aw(
        el: Element | null,
        keyframes: Keyframe[],
        duration: number,
        o: AnimateOptions = {},
    ): Promise<void> {
        if (!el) {
            return Promise.resolve();
        }

        const animation = el.animate(keyframes, {
            duration,
            delay: o.d ?? 0,
            easing: o.e ?? 'cubic-bezier(.2,.8,.2,1)',
            fill: o.fill ?? 'forwards',
            iterations: o.it ?? 1,
            direction: o.dir ?? 'normal',
        });

        return animation.finished.then(
            () => undefined,
            () => undefined,
        );
    }

    private sleep(ms: number): Promise<void> {
        const token = this.token;

        return new Promise((resolve, reject) =>
            setTimeout(
                () => (token === this.token ? resolve() : reject(ABORT)),
                ms,
            ),
        );
    }

    private center(el: Element): {
        x: number;
        y: number;
        w: number;
        h: number;
    } {
        const r = el.getBoundingClientRect();

        return {
            x: r.left + r.width / 2,
            y: r.top + r.height / 2,
            w: r.width,
            h: r.height,
        };
    }

    private vibrate(pattern: number | number[]): void {
        try {
            navigator.vibrate?.(pattern);
        } catch {
            // sem vibração
        }
    }

    private shake(): boolean {
        return !this.reduced;
    }

    // ── entrada ────────────────────────────────────────────────────────

    introIdle(): void {
        this.a(
            this.$('ballWrap'),
            [
                { opacity: 0, transform: 'translateY(-60px) scale(.7)' },
                {
                    opacity: 1,
                    transform: 'translateY(8px) scale(1.03)',
                    offset: 0.7,
                },
                { opacity: 1, transform: 'none' },
            ],
            900,
            { e: 'cubic-bezier(.3,.8,.4,1)', fill: 'none' },
        );
    }

    /** Recarregou com o pack já aberto: bolas no chão, abertas como sprite. */
    resumeBalls(): void {
        this.setStyle(this.$('idle'), { visibility: 'hidden' });
        this.setStyle(this.$('slots'), { opacity: '1' });
        this.state.slots.forEach((slot, i) => {
            if (slot.specimen) {
                this.setStyle(this.slot(i, 'mball'), { opacity: '0' });
                this.setStyle(this.slot(i, 'sspr'), { opacity: '1' });
                this.setStyle(this.slot(i, 'slabel'), { opacity: '1' });

                if (slot.specimen.isShiny) {
                    this.addSpark(i);
                }
            } else {
                this.setStyle(this.slot(i, 'mball'), { opacity: '1' });
                this.startWobble(i);
            }
        });
    }

    showSummaryNow(): void {
        const sum = this.$('sum');
        this.setStyle(this.$('idle'), { visibility: 'hidden' });
        this.setStyle(sum, { visibility: 'visible', clipPath: 'none' });
        this.root
            .querySelectorAll<HTMLElement>('[data-card]')
            .forEach((card) => (card.style.opacity = '1'));
    }

    private setStyle(
        el: HTMLElement | null,
        styles: Partial<CSSStyleDeclaration>,
    ): void {
        if (el) {
            Object.assign(el.style, styles);
        }
    }

    // ── carga ─────────────────────────────────────────────────────────

    startCharge(): void {
        if (this.state.phase !== 'idle' || this.state.error) {
            return;
        }

        this.sfx.init();
        this.charging = true;
    }

    private onPointerMove = (event: PointerEvent): void => {
        this.pointer = { x: event.clientX, y: event.clientY };
    };

    private onPointerUp = (): void => {
        this.charging = false;
    };

    private onKeyDown = (event: KeyboardEvent): void => {
        const target = event.target as HTMLElement | null;

        if (
            event.repeat ||
            target?.tagName === 'INPUT' ||
            (event.code !== 'Space' && event.code !== 'Enter')
        ) {
            return;
        }

        // Enter/Espaço num botão de bola já dispara o clique dele.
        if (target?.dataset.ball !== undefined && !this.tapResolve) {
            return;
        }

        event.preventDefault();

        if (this.state.phase === 'idle') {
            this.startCharge();
        } else if (this.tapResolve) {
            this.tapResolve();
        } else if (this.state.phase === 'balls') {
            const next = this.state.slots.findIndex(
                (slot) => !slot.specimen && !slot.pending,
            );

            if (next >= 0) {
                void this.openSlot(next);
            }
        }
    };

    private onKeyUp = (event: KeyboardEvent): void => {
        if (event.code === 'Space' || event.code === 'Enter') {
            this.charging = false;
        }
    };

    /** Toque em qualquer lugar: continua a cerimônia. */
    tap(): void {
        this.tapResolve?.();
    }

    private idleFrame(dt: number): void {
        this.charge = Math.max(
            0,
            Math.min(1, this.charge + (this.charging ? dt / 1.35 : -dt / 0.45)),
        );
        const ch = this.charge;
        const e2 = ch * ch;
        this.phase += dt * (3 + e2 * 27) * Math.PI * 2;
        this.idleTime += dt;
        const shake = this.$('ballShake');
        const wrap = this.$('ballWrap');

        if (!shake || !wrap) {
            return;
        }

        const bc = this.center(wrap);
        const target = this.pointer
            ? Math.max(
                  -1,
                  Math.min(1, (this.pointer.x - bc.x) / (innerWidth / 2)),
              ) * 7
            : 0;
        this.tilt += (target - this.tilt) * Math.min(1, dt * 5);
        const amp = ch > 0 ? (this.shake() ? 1 + e2 * 12 : 1 + e2 * 3) : 0;
        const fy = Math.sin(this.idleTime * 1.8) * 7 * (1 - ch);
        const rot = Math.sin(this.phase) * amp + this.tilt * (1 - ch);
        const tx = this.shake() ? Math.sin(this.phase * 1.3) * e2 * 5 : 0;
        shake.style.transform = `translate(${tx}px, ${fy}px) rotate(${rot}deg) scale(${1 + e2 * 0.06})`;
        const shadow = this.$('ballShadow');

        if (shadow) {
            shadow.style.transform = `scaleX(${1 - fy / 60})`;
        }

        this.$('ringFill')?.setAttribute(
            'stroke-dashoffset',
            String(301.6 * (1 - ch)),
        );
        const button = this.$('bBtn');

        if (button) {
            button.style.background = ch > 0.02 ? '#ffffff' : '#e4e7eb';
            button.style.boxShadow = `0 0 ${4 + ch * 28}px ${ch * 9}px rgba(242,77,77,${ch * 0.9}), inset 0 -3px 0 rgba(0,0,0,0.12)`;
        }

        const glow = this.$('chargeGlow');

        if (glow) {
            glow.style.opacity = String(ch);
        }

        this.sfx.rumble(ch);
        const k = Math.floor(this.phase / Math.PI);

        if (k !== this.lastTick) {
            this.lastTick = k;

            if (ch > 0.04) {
                this.sfx.tick(ch);
            }
        }

        if (ch > 0 && Math.random() < dt * 70 * ch) {
            this.fx.attract(bc.x, bc.y);
        }

        if (ch >= 1) {
            void this.burst().catch(() => undefined);
        }
    }

    // ── explosão ──────────────────────────────────────────────────────

    private async burst(): Promise<void> {
        this.state.phase = 'run';
        this.charging = false;
        this.charge = 0;
        this.sfx.rumble(0);

        // O POST que sorteia sai junto com a explosão; a cena espera ele
        // no escuro antes de soltar as bolas.
        const opening = this.api.openPack();
        this.sfx.burst();
        this.vibrate([30, 20, 60]);
        const bc = this.center(this.$('ballWrap') as HTMLElement);
        this.fx.dropAttracted();
        this.setStyle(this.$('flash'), { opacity: '1' });
        this.fx.emit(bc.x, bc.y, 110, {
            cols: ['#f24d4d', '#ffffff', '#ffd9d9', '#ff8a8a'],
            sp: [4, 16],
            r: [1.5, 5],
            g: 0.22,
            drag: 0.95,
            life: [0.6, 1.3],
        });
        this.fx.emit(bc.x, bc.y, 40, {
            cols: ['#ffffff', '#ffd9d9'],
            sp: [10, 22],
            r: [1.5, 3],
            g: 0.05,
            drag: 0.92,
            shape: 'spark',
            life: [0.3, 0.6],
        });
        this.a(
            this.$('bTop'),
            [
                { transform: 'none' },
                {
                    transform: 'translate(-30%, -170%) rotate(-60deg)',
                    opacity: 0,
                },
            ],
            750,
            { e: 'cubic-bezier(.1,.7,.3,1)' },
        );
        this.a(
            this.$('bBot'),
            [
                { transform: 'none' },
                { transform: 'translate(26%, 160%) rotate(40deg)', opacity: 0 },
            ],
            750,
            { e: 'cubic-bezier(.1,.7,.3,1)' },
        );

        if (this.shake()) {
            this.a(
                this.$('stage'),
                [
                    { transform: 'none' },
                    { transform: 'translate(-12px, 8px)' },
                    { transform: 'translate(10px, -6px)' },
                    { transform: 'translate(-6px, 4px)' },
                    { transform: 'none' },
                ],
                380,
                { e: 'linear', fill: 'none' },
            );
        }

        this.a(this.$('chargeGlow'), [{ opacity: 1 }, { opacity: 0 }], 600);
        await this.sleep(90);
        this.setStyle(this.$('black'), { opacity: '1' });
        this.a(this.$('flash'), [{ opacity: 1 }, { opacity: 0 }], 300, {
            e: 'ease-out',
        });
        await this.sleep(300);
        this.setStyle(this.$('idle'), { visibility: 'hidden' });
        this.fx.clear();

        let result: { openingId: number; nextPackAt: string | null };

        try {
            [result] = await Promise.all([opening, this.sleep(520)]);
        } catch (error) {
            if (error instanceof Aborted) {
                throw error;
            }

            this.state.error =
                error instanceof Error
                    ? error.message
                    : 'Não deu pra abrir o pack.';
            this.a(this.$('black'), [{ opacity: 1 }, { opacity: 0 }], 500, {
                e: 'ease-out',
            });

            return;
        }

        this.state.openingId = result.openingId;
        this.state.nextPackAt = result.nextPackAt;
        this.state.phase = 'balls';
        await nextTick();
        this.setStyle(this.$('slots'), { opacity: '1' });
        this.a(this.$('black'), [{ opacity: 1 }, { opacity: 0 }], 500, {
            e: 'ease-out',
        });
        const token = this.token;

        for (let i = 0; i < this.state.slots.length; i++) {
            const delay = 160 + i * 95;
            this.a(
                this.slot(i, 'mball'),
                [
                    { opacity: 0, transform: 'translateY(-110px) scale(.6)' },
                    {
                        opacity: 1,
                        transform: 'translateY(4px) scale(1.06, .92)',
                        offset: 0.72,
                    },
                    { opacity: 1, transform: 'none' },
                ],
                560,
                { d: delay, e: 'cubic-bezier(.4,.1,.6,1)' },
            );
            setTimeout(
                () => token === this.token && this.sfx.land(i),
                delay + 400,
            );
        }

        await this.sleep(160 + 4 * 95 + 560);
        this.state.slots.forEach((_, i) => this.startWobble(i));
    }

    // ── bolas no chão ─────────────────────────────────────────────────

    /** Balanço de espera: ±4° a cada ~2s, com fase diferente por bola. */
    private startWobble(i: number): void {
        const shake = this.slot(i, 'mShake');

        if (!shake || this.reduced) {
            return;
        }

        this.wobbles.get(i)?.cancel();
        const animation = shake.animate(
            [
                { transform: 'rotate(0deg)' },
                { transform: 'rotate(-4deg)', offset: 0.12 },
                { transform: 'rotate(4deg)', offset: 0.24 },
                { transform: 'rotate(-2deg)', offset: 0.34 },
                { transform: 'rotate(0deg)', offset: 0.42 },
                { transform: 'rotate(0deg)' },
            ],
            {
                duration: 2000,
                iterations: Infinity,
                delay: i * 370,
                easing: 'ease-in-out',
            },
        );
        this.wobbles.set(i, animation);
    }

    private stopWobble(i: number): void {
        this.wobbles.get(i)?.cancel();
        this.wobbles.delete(i);
    }

    /** Clique numa bola fechada: pergunta o conteúdo ao servidor e encena. */
    async openSlot(i: number): Promise<void> {
        const slot = this.state.slots[i];

        if (
            this.state.phase !== 'balls' ||
            this.state.locked ||
            !slot ||
            slot.specimen ||
            slot.pending ||
            this.state.openingId === null
        ) {
            return;
        }

        this.sfx.init();
        slot.pending = true;
        let result: Awaited<ReturnType<PackApi['reveal']>>;

        try {
            result = await this.api.reveal(this.state.openingId, slot.slot);
        } catch {
            slot.pending = false;
            this.state.error = 'Não deu pra abrir essa bola. Tente de novo.';

            return;
        }

        const specimen = result.specimen;
        const ceremony = hasCeremony(specimen);

        if (ceremony) {
            this.state.locked = true;
        }

        this.stopWobble(i);
        slot.specimen = specimen;
        slot.pending = false;

        if (result.receipt) {
            this.state.receipt = result.receipt;
        }

        await nextTick();
        await this.spriteReady(this.slot(i, 'sspr') as HTMLImageElement | null);

        try {
            if (ceremony) {
                await this.ceremony(i, specimen);
            } else {
                await this.pop(i, specimen);
            }
        } finally {
            this.state.locked = false;
        }

        if (specimen.isShiny) {
            this.addSpark(i);
        }

        if (this.state.slots.every((s) => s.specimen)) {
            await this.sleep(700);
            await this.toSummary();
        }
    }

    private spriteReady(img: HTMLImageElement | null): Promise<void> {
        if (!img || (img.complete && img.naturalWidth)) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            const done = () => resolve();
            img.addEventListener('load', done, { once: true });
            img.addEventListener('error', done, { once: true });
            setTimeout(done, 1500);
        });
    }

    private addSpark(i: number): void {
        this.fx.sparkTargets.push(() => this.slot(i, 'sspr'));
    }

    // ── pop (comum/incomum) ───────────────────────────────────────────

    private async pop(i: number, specimen: PackSpecimen): Promise<void> {
        const ball = this.slot(i, 'mball');
        await this.aw(
            this.slot(i, 'mShake'),
            [
                { transform: 'none' },
                { transform: 'rotate(-14deg)' },
                { transform: 'rotate(10deg)' },
                { transform: 'none' },
            ],
            220,
            { e: 'ease-in-out', fill: 'none' },
        );
        this.sfx.pop(i, specimen.tier);
        const c = this.center(ball as HTMLElement);
        const common = specimen.tier === 'comum';
        this.fx.emit(c.x, c.y - 6, common ? 12 : 22, {
            cols: common
                ? ['#c3cad4', '#7d8794']
                : [glowOf(specimen), '#ffd9b8'],
            sp: [1.5, 5],
            r: [1.2, 3],
            g: 0.04,
            drag: 0.92,
            life: [0.4, 0.8],
        });
        this.a(
            this.slot(i, 'mTop'),
            [{ transform: 'none' }, { transform: 'rotate(-125deg)' }],
            280,
            { e: 'cubic-bezier(.3,1.5,.5,1)' },
        );
        this.a(
            ball,
            [
                { opacity: 1, transform: 'none' },
                { opacity: 0, transform: 'translateY(10px) scale(.5)' },
            ],
            300,
            { d: 110 },
        );
        this.a(
            this.slot(i, 'sspr'),
            [
                { opacity: 0, transform: 'translateY(30px) scale(.2)' },
                {
                    opacity: 1,
                    transform: 'translateY(-10px) scale(1.12)',
                    offset: 0.6,
                },
                { opacity: 1, transform: 'none' },
            ],
            480,
            { d: 60, e: 'cubic-bezier(.2,.9,.3,1)' },
        );
        this.a(
            this.slot(i, 'slabel'),
            [
                { opacity: 0, transform: 'translateY(8px)' },
                { opacity: 1, transform: 'none' },
            ],
            320,
            { d: 220 },
        );
        await this.sleep(300);
    }

    // ── cerimônia (raro, lendário, shiny) ─────────────────────────────

    private async ceremony(i: number, specimen: PackSpecimen): Promise<void> {
        const col = glowOf(specimen);
        const legendary = specimen.tier === 'lendario';
        const short = this.reduced;
        this.$('cer')
            ?.getAnimations({ subtree: true })
            .forEach((animation) => animation.cancel());
        this.state.ceremony = specimen;
        await nextTick();
        await this.spriteReady(this.$('cimg') as HTMLImageElement | null);

        const ball = this.slot(i, 'mball') as HTMLElement;
        const ballShake = this.slot(i, 'mShake');
        // 1. Treme com o brilho da cor (é aqui que a raridade aparece).
        this.a(
            ballShake,
            [
                { filter: 'drop-shadow(0 0 0px rgba(0,0,0,0))' },
                { filter: `drop-shadow(0 0 12px ${col})` },
            ],
            500,
        );
        this.a(
            ballShake,
            [
                { transform: 'none' },
                { transform: 'translateX(-3px) rotate(-6deg)' },
                { transform: 'translateX(3px) rotate(6deg)' },
                { transform: 'none' },
            ],
            120,
            { it: 5, fill: 'none' },
        );
        await this.sleep(650);

        // 2. Congela: as outras somem, escurece, o glow do fundo vira a cor.
        this.fx.frozen = true;
        this.sfx.freeze();
        this.vibrate(20);
        this.state.slots.forEach((_, j) => {
            if (j !== i) {
                this.a(
                    this.slot(j),
                    [
                        { opacity: 1, transform: 'none' },
                        {
                            opacity: 0.06,
                            transform: 'translateY(50px) scale(.94)',
                        },
                    ],
                    600,
                );
            }
        });
        this.a(this.slot(i, 'slabel'), [{ opacity: 0 }, { opacity: 0 }], 1);
        this.a(
            this.$('dark'),
            [
                { opacity: 0 },
                { opacity: legendary || specimen.isShiny ? 0.72 : 0.58 },
            ],
            900,
        );
        this.a(this.$('glowA'), [{ opacity: 1 }, { opacity: 0 }], 900);
        const glowB = this.$('glowB');
        this.setStyle(glowB, {
            background: `radial-gradient(70% 45% at 50% 46%, ${rgba(col, 0.16)}, ${rgba(col, 0)} 100%)`,
        });
        this.a(glowB, [{ opacity: 0 }, { opacity: 1 }], 1100);

        // 3. A bola voa pro centro.
        const centerBall = this.$('cball') as HTMLElement;
        const from = this.center(ball);
        const to = this.center(centerBall);
        this.a(ball, [{ opacity: 0 }, { opacity: 0 }], 1);
        this.a(
            centerBall,
            [
                {
                    opacity: 1,
                    transform: `translate(${from.x - to.x}px, ${from.y - to.y}px) scale(${from.w / to.w})`,
                },
                { opacity: 1, transform: 'none' },
            ],
            850,
            { e: 'cubic-bezier(.2,.9,.2,1)' },
        );
        this.a(
            this.$('cShake'),
            [
                { filter: `drop-shadow(0 0 12px ${col})` },
                { filter: `drop-shadow(0 0 26px ${col})` },
            ],
            850,
        );
        this.sfx.whoosh(true);
        await this.sleep(1050);

        // 4. Três balanços com "toc".
        const rays = this.$('rays');

        if (legendary) {
            this.a(rays, [{ opacity: 0 }, { opacity: 0.85 }], 3200, {
                e: 'ease-in',
            });

            if (!short) {
                this.a(
                    rays,
                    [
                        { transform: 'rotate(0deg)' },
                        { transform: 'rotate(360deg)' },
                    ],
                    50000,
                    { it: Infinity, e: 'linear', fill: 'none' },
                );
            }
        }

        const pauses = legendary ? [520, 680, 980] : [420, 560, 820];

        for (let k = 0; k < 3; k++) {
            this.sfx.tok(k);
            this.vibrate(12);
            this.a(
                this.$('cShake'),
                [
                    { transform: 'rotate(0)' },
                    { transform: 'rotate(-24deg)', offset: 0.25 },
                    { transform: 'rotate(18deg)', offset: 0.55 },
                    { transform: 'rotate(-6deg)', offset: 0.8 },
                    { transform: 'rotate(0)' },
                ],
                560,
                { e: 'ease-in-out', fill: 'none' },
            );
            this.a(
                this.$('cBtn'),
                [
                    { background: '#e4e7eb' },
                    { background: '#ffffff', boxShadow: `0 0 14px 4px ${col}` },
                    { background: '#e4e7eb' },
                ],
                560,
                { fill: 'none' },
            );

            if (specimen.isShiny) {
                const c = this.center(centerBall);
                setTimeout(() => this.sfx.glint(), 200);
                this.fx.emit(c.x, c.y, 5, {
                    cols: [CYAN, '#ffffff'],
                    shape: 'star',
                    sp: [0.5, 2],
                    r: [4, 8],
                    g: -0.01,
                    drag: 0.96,
                    free: true,
                    life: [0.6, 1],
                    jit: 50,
                });
            }

            if (legendary && this.shake()) {
                this.a(
                    this.$('stage'),
                    [
                        { transform: 'none' },
                        { transform: 'translate(-3px, 2px)' },
                        { transform: 'translate(3px, -1px)' },
                        { transform: 'none' },
                    ],
                    90,
                    { it: 5, fill: 'none', e: 'linear' },
                );
            }

            await this.sleep(560 + (short ? pauses[k] * 0.3 : pauses[k]));
        }

        // 5. Abre: halo, feixe, partículas (shiny: flash e estrelas).
        this.sfx.open();
        this.vibrate([20, 30, 40]);
        const cc = this.center(centerBall);
        this.a(
            this.$('cTop'),
            [{ transform: 'none' }, { transform: 'rotate(-130deg)' }],
            380,
            { e: 'cubic-bezier(.3,1.5,.5,1)' },
        );
        this.a(
            centerBall,
            [
                { opacity: 1, transform: 'none' },
                { opacity: 0, transform: 'translateY(50px) scale(.7)' },
            ],
            520,
            { d: 260 },
        );
        const halo = this.$('halo');
        this.setStyle(halo, {
            background: `radial-gradient(circle, ${rgba(col, 0.5)}, ${rgba(col, 0)} 65%)`,
        });
        this.a(
            halo,
            [
                { opacity: 0, transform: 'scale(.3)' },
                { opacity: 1, transform: 'scale(1)' },
            ],
            800,
            { e: 'cubic-bezier(.2,.9,.2,1)' },
        );
        const beam = this.$('beam');
        this.setStyle(beam, {
            background: `radial-gradient(closest-side at 50% 62%, ${rgba(col, 0.5)}, ${rgba(col, 0)} 100%)`,
        });
        this.a(
            beam,
            [
                { opacity: 0, transform: 'scaleY(0)' },
                { opacity: 1, transform: 'scaleY(1)' },
            ],
            650,
            { e: 'cubic-bezier(.2,.9,.2,1)' },
        );
        this.fx.emit(cc.x, cc.y, 60, {
            cols: [col, '#ffffff', col],
            sp: [3, 12],
            r: [1.5, 4],
            g: 0.06,
            drag: 0.94,
            free: true,
            life: [0.6, 1.4],
        });

        if (specimen.isShiny) {
            this.a(
                this.$('flash'),
                [{ opacity: 1 }, { opacity: 1, offset: 0.25 }, { opacity: 0 }],
                180,
                { e: 'linear' },
            );
            this.fx.emit(cc.x, cc.y, 34, {
                cols: [CYAN, '#ffffff'],
                shape: 'star',
                sp: [2, 9],
                r: [4, 9],
                g: 0,
                drag: 0.93,
                free: true,
                life: [0.8, 1.6],
            });
        }

        // 6. Silhueta sobe e a cor entra.
        const sprite = this.$('cspr');
        const img = this.$('cimg');
        const silhouette = `brightness(0) drop-shadow(0 0 16px ${col})`;
        this.a(img, [{ filter: silhouette }, { filter: silhouette }], 1);
        this.a(
            sprite,
            [
                { opacity: 0, transform: 'translateY(90px) scale(.25)' },
                { opacity: 1, transform: 'none' },
            ],
            short ? 700 : 1600,
            { d: 120, e: 'cubic-bezier(.16,1,.3,1)' },
        );
        this.sfx.rise();
        await this.sleep(short ? 850 : 1750);
        this.a(
            img,
            [
                { filter: silhouette },
                { filter: `brightness(1) drop-shadow(0 0 0px ${col})` },
            ],
            460,
            { e: 'ease-out' },
        );

        if (!specimen.isShiny) {
            this.a(this.$('flash'), [{ opacity: 0.28 }, { opacity: 0 }], 360, {
                e: 'ease-out',
            });
        }

        this.sfx.chord(specimen.tier, specimen.isShiny);
        this.vibrate(30);
        const ic = this.center(img as HTMLElement);
        this.fx.emit(ic.x, ic.y, 44, {
            cols: [col, '#ffffff'],
            sp: [5, 11],
            r: [1.5, 3.5],
            g: 0,
            drag: 0.9,
            free: true,
            shape: 'spark',
            life: [0.4, 0.8],
        });
        this.a(
            this.$('cfloat'),
            [
                { transform: 'translateY(0)' },
                { transform: 'translateY(-10px)' },
            ],
            1700,
            { it: Infinity, dir: 'alternate', e: 'ease-in-out', fill: 'none' },
        );

        if (specimen.isShiny) {
            const shine = this.$('cshine');
            const mask = `url("${spriteOf(specimen)}")`;
            this.setStyle(shine, {
                maskImage: mask,
                webkitMaskImage: mask,
            } as Partial<CSSStyleDeclaration>);
            this.a(
                shine,
                [
                    { opacity: 1, backgroundPosition: '100% 0' },
                    { opacity: 1, backgroundPosition: '0% 0' },
                ],
                950,
                { d: 250, it: 2, e: 'ease-in-out', fill: 'none' },
            );
        }

        if (specimen.isShiny || legendary) {
            this.fx.twinkle = {
                el: img as HTMLElement,
                col: specimen.isShiny ? CYAN : '#FFC94D',
            };
        }

        await this.sleep(450);

        // 7. Nome letra a letra, chips e meta.
        const letters = this.$('cname')?.querySelectorAll('[data-l]') ?? [];
        letters.forEach((letter, k) =>
            this.a(
                letter,
                [
                    {
                        opacity: 0,
                        transform: 'translateY(40px) rotate(10deg) scale(.7)',
                    },
                    { opacity: 1, transform: 'none' },
                ],
                560,
                { d: k * 48, e: 'cubic-bezier(.2,1.5,.4,1)' },
            ),
        );
        await this.sleep(letters.length * 48 + 320);
        this.a(
            this.$('cchips'),
            [
                { opacity: 0, transform: 'translateY(10px)' },
                { opacity: 1, transform: 'none' },
            ],
            420,
        );
        await this.sleep(180);
        this.a(
            this.$('cmeta'),
            [
                { opacity: 0, transform: 'translateY(10px)' },
                { opacity: 1, transform: 'none' },
            ],
            420,
        );
        await this.sleep(600);

        // 8. Espera o toque.
        const next = this.$('cnext');
        this.a(next, [{ opacity: 0 }, { opacity: 1 }], 400);
        this.a(next, [{ opacity: 1 }, { opacity: 0.35 }], 900, {
            d: 400,
            it: Infinity,
            dir: 'alternate',
            e: 'ease-in-out',
            fill: 'none',
        });
        await new Promise<void>((resolve) => (this.tapResolve = resolve));
        this.tapResolve = null;
        this.fx.twinkle = null;
        this.sfx.whoosh(false);

        // 9. O sprite volta pro slot real (medido agora, no layout atual).
        this.a(this.$('cinfo'), [{ opacity: 1 }, { opacity: 0 }], 260);
        this.a(next, [{ opacity: 0 }, { opacity: 0 }], 1);
        const slotImg = this.slot(i, 'sspr') as HTMLElement;
        const t1 = this.center(
            slotImg.getBoundingClientRect().width
                ? slotImg
                : (this.slot(i, 'sbox') as HTMLElement),
        );
        const t2 = this.center(img as HTMLElement);
        this.$('cfloat')
            ?.getAnimations()
            .forEach((animation) => animation.cancel());
        this.a(
            sprite,
            [
                { opacity: 1, transform: 'none' },
                {
                    opacity: 1,
                    transform: `translate(${t1.x - t2.x}px, ${t1.y - t2.y}px) scale(${Math.max(0.15, t1.h / t2.h)})`,
                },
            ],
            680,
            { e: 'cubic-bezier(.6,0,.2,1)' },
        );
        (['dark', 'glowB', 'halo', 'beam', 'rays', 'glowA'] as const).forEach(
            (key) => {
                const el = this.$(key);

                if (el) {
                    this.a(
                        el,
                        [
                            { opacity: getComputedStyle(el).opacity },
                            { opacity: key === 'glowA' ? 1 : 0 },
                        ],
                        700,
                    );
                }
            },
        );
        this.state.slots.forEach((_, j) => {
            if (j !== i) {
                this.a(
                    this.slot(j),
                    [
                        {
                            opacity: 0.06,
                            transform: 'translateY(50px) scale(.94)',
                        },
                        { opacity: 1, transform: 'none' },
                    ],
                    700,
                    { d: 150 },
                );
            }
        });
        await this.sleep(680);
        this.a(sprite, [{ opacity: 0 }, { opacity: 0 }], 1);
        this.a(
            slotImg,
            [
                { opacity: 1, transform: 'scale(1.18)' },
                { opacity: 1, transform: 'none' },
            ],
            360,
            { e: 'cubic-bezier(.2,1.5,.4,1)' },
        );
        this.a(
            this.slot(i, 'slabel'),
            [
                { opacity: 0, transform: 'translateY(8px)' },
                { opacity: 1, transform: 'none' },
            ],
            320,
        );
        const sc = this.center(slotImg);
        this.fx.emit(sc.x, sc.y, 18, {
            cols: [col, '#ffffff'],
            sp: [1.5, 5],
            r: [1.2, 3],
            g: 0.03,
            drag: 0.92,
            life: [0.4, 0.8],
        });
        this.sfx.land(i);
        this.fx.frozen = false;
        await this.sleep(450);
        this.state.ceremony = null;
    }

    // ── resumo ────────────────────────────────────────────────────────

    private async toSummary(): Promise<void> {
        this.state.phase = 'summary';
        this.fx.sparkTargets = [];
        await nextTick();
        await this.sleep(40);
        this.sfx.summary();
        const sum = this.$('sum');

        if (sum) {
            sum.scrollTop = 0;
        }

        this.setStyle(sum, { visibility: 'visible' });
        this.a(
            sum,
            [
                { clipPath: 'circle(0% at 50% 85%)' },
                { clipPath: 'circle(150% at 50% 85%)' },
            ],
            1050,
            { e: 'cubic-bezier(.7,0,.2,1)' },
        );
        this.a(
            this.$('sumHead'),
            [
                { opacity: 0, transform: 'translateY(24px)' },
                { opacity: 1, transform: 'none' },
            ],
            700,
            { d: 380, fill: 'backwards' },
        );
        this.root
            .querySelectorAll<HTMLElement>('[data-card]')
            .forEach((card, k) => {
                card.style.opacity = '1';
                this.a(
                    card,
                    [
                        {
                            opacity: 0,
                            transform:
                                'translateY(70px) rotateX(-28deg) scale(.9)',
                        },
                        { opacity: 1, transform: 'none' },
                    ],
                    820,
                    {
                        d: 480 + k * 90,
                        e: 'cubic-bezier(.2,1.3,.4,1)',
                        fill: 'backwards',
                    },
                );
            });
        this.a(
            this.$('sumFoot'),
            [
                { opacity: 0, transform: 'translateY(20px)' },
                { opacity: 1, transform: 'none' },
            ],
            600,
            { d: 1000, fill: 'backwards' },
        );
    }
}
